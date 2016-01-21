<?php
/**
 * Data class for event RSVPs
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Data class for event RSVPs
 *
 * @category Event
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      Managed_DataObject
 */
class RSVP extends Managed_DataObject
{
    const POSITIVE = 'http://activitystrea.ms/schema/1.0/rsvp-yes';
    const POSSIBLE = 'http://activitystrea.ms/schema/1.0/rsvp-maybe';
    const NEGATIVE = 'http://activitystrea.ms/schema/1.0/rsvp-no';

    public $__table = 'rsvp'; // table name
    public $id;                // varchar(36) UUID
    public $uri;               // varchar(191)   not 255 because utf8mb4 takes more space
    public $profile_id;        // int
    public $event_uri;         // varchar(191)   not 255 because utf8mb4 takes more space
    public $response;            // tinyint
    public $created;           // datetime

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Plan to attend event',
            'fields' => array(
                'id' => array('type' => 'char',
                              'length' => 36,
                              'not null' => true,
                              'description' => 'UUID'),
                'uri' => array('type' => 'varchar',
                               'length' => 191,
                               'not null' => true),
                'profile_id' => array('type' => 'int'),
                'event_uri' => array('type' => 'varchar',
                              'length' => 191,
                              'not null' => true,
                              'description' => 'Event URI'),
                'response' => array('type' => 'char',
                                  'length' => '1',
                                  'description' => 'Y, N, or ? for three-state yes, no, maybe'),
                'created' => array('type' => 'datetime',
                                   'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'rsvp_uri_key' => array('uri'),
                'rsvp_profile_event_key' => array('profile_id', 'event_uri'),
            ),
            'foreign keys' => array('rsvp_event_uri_key' => array('happening', array('event_uri' => 'uri')),
                                    'rsvp_profile_id__key' => array('profile', array('profile_id' => 'id'))),
            'indexes' => array('rsvp_created_idx' => array('created')),
        );
    }

    static public function beforeSchemaUpdate()
    {
        $table = strtolower(get_called_class());
        $schema = Schema::get();
        $schemadef = $schema->getTableDef($table);

        // 2015-12-31 RSVPs refer to Happening by event_uri now, not event_id. Let's migrate!
        if (isset($schemadef['fields']['event_uri'])) {
            // We seem to have already migrated, good!
            return;
        }

        // this is a "normal" upgrade from StatusNet for example
        echo "\nFound old $table table, upgrading it to add 'event_uri' field...";

        $schemadef['fields']['event_uri'] = array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'Event URI');
        $schema->ensureTable($table, $schemadef);

        $rsvp = new RSVP();
        $rsvp->find();
        while ($rsvp->fetch()) {
            $event = Happening::getKV('id', $rsvp->event_id);
            if (!$event instanceof Happening) {
                $rsvp->delete();
                continue;
            }
            $orig = clone($rsvp);
            $rsvp->event_uri = $event->uri;
            $rsvp->updateWithKeys($orig);
        }
        print "DONE.\n";
        print "Resuming core schema upgrade...";
    }

    static function saveActivityObject(Activity $act, Notice $stored)
    {
        $target = Notice::getByKeys(array('uri'=>$act->target->id));
        if (!ActivityUtils::compareTypes($target->getObjectType(), [ Happening::OBJECT_TYPE ])) {
            throw new ClientException('RSVP not aimed at a Happening');
        }

        // FIXME: Maybe we need some permission handling here, though I think it's taken care of in saveActivity?

        try {
            $other = RSVP::getByKeys( [ 'profile_id' => $stored->getProfile()->getID(),
                                        'event_uri' => $target->getUri(),
                                      ] );
            // TRANS: Client exception thrown when trying to save an already existing RSVP ("please respond").
            throw new AlreadyFulfilledException(_m('RSVP already exists.'));
        } catch (NoResultException $e) {
            // No previous RSVP, so go ahead and add.
        }

        $rsvp = new RSVP();
        $rsvp->id          = UUID::gen();   // remove this
        $rsvp->uri         = $stored->getUri();
        $rsvp->profile_id  = $stored->getProfile()->getID();
        $rsvp->event_uri   = $target->getUri();
        $rsvp->response    = self::codeFor($stored->getVerb());
        $rsvp->created     = $stored->getCreated();

        $rsvp->insert();

        self::blow('rsvp:for-event:%s', $target->getUri());

        return $rsvp;
    }

    static public function getObjectType()
    {
        return ActivityObject::ACTIVITY;
    }

    public function asActivityObject()
    {
        $happening = $this->getEvent();

        $actobj = new ActivityObject();
        $actobj->id = $this->getUri();
        $actobj->type = self::getObjectType();
        $actobj->title = $this->asString();
        $actobj->content = $this->asString();
        $actobj->target = array($happening->asActivityObject());

        return $actobj;
    }

    static function codeFor($verb)
    {
        switch (true) {
        case ActivityUtils::compareVerbs($verb, [RSVP::POSITIVE]):
            return 'Y';
            break;
        case ActivityUtils::compareVerbs($verb, [RSVP::NEGATIVE]):
            return 'N';
            break;
        case ActivityUtils::compareVerbs($verb, [RSVP::POSSIBLE]):
            return '?';
            break;
        default:
            // TRANS: Exception thrown when requesting an undefined verb for RSVP.
            throw new Exception(sprintf(_m('Unknown verb "%s".'),$verb));
        }
    }

    static function verbFor($code)
    {
        switch ($code) {
        case 'Y':
        case 'yes':
            return RSVP::POSITIVE;
            break;
        case 'N':
        case 'no':
            return RSVP::NEGATIVE;
            break;
        case '?':
        case 'maybe':
            return RSVP::POSSIBLE;
            break;
        default:
            // TRANS: Exception thrown when requesting an undefined code for RSVP.
            throw new ClientException(sprintf(_m('Unknown code "%s".'), $code));
        }
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getEventUri()
    {
        return $this->event_uri;
    }

    public function getStored()
    {
        return Notice::getByKeys(['uri' => $this->getUri()]);
    }

    static function fromStored(Notice $stored)
    {
        return self::getByKeys(['uri' => $stored->getUri()]);
    }

    static function byEventAndActor(Happening $event, Profile $actor)
    {
        return self::getByKeys(['event_uri' => $event->getUri(),
                                'profile_id' => $actor->getID()]);
    }

    static function forEvent(Happening $event)
    {
        $keypart = sprintf('rsvp:for-event:%s', $event->getUri());

        $idstr = self::cacheGet($keypart);

        if ($idstr !== false) {
            $ids = explode(',', $idstr);
        } else {
            $ids = array();

            $rsvp = new RSVP();

            $rsvp->selectAdd();
            $rsvp->selectAdd('id');

            $rsvp->event_uri = $event->getUri();

            if ($rsvp->find()) {
                while ($rsvp->fetch()) {
                    $ids[] = $rsvp->id;
                }
            }
            self::cacheSet($keypart, implode(',', $ids));
        }

        $rsvps = array(RSVP::POSITIVE => array(),
                       RSVP::NEGATIVE => array(),
                       RSVP::POSSIBLE => array());

        foreach ($ids as $id) {
            $rsvp = RSVP::getKV('id', $id);
            if (!empty($rsvp)) {
                $verb = self::verbFor($rsvp->response);
                $rsvps[$verb][] = $rsvp;
            }
        }

        return $rsvps;
    }

    function getProfile()
    {
        return Profile::getByID($this->profile_id);
    }

    function getEvent()
    {
        return Happening::getByKeys(['uri' => $this->getEventUri()]);
    }

    function asHTML()
    {
        return self::toHTML($this->getProfile(),
                            $this->getEvent(),
                            $this->response);
    }

    function asString()
    {
        return self::toString($this->getProfile(),
                              $this->getEvent(),
                              $this->response);
    }

    static function toHTML(Profile $profile, Happening $event, $response)
    {
        $fmt = null;

        switch ($response) {
        case 'Y':
            // TRANS: HTML version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile URL, %2$s a profile name,
            // TRANS: %3$s is an event URL, %4$s an event title.
            $fmt = _m("<span class='automatic event-rsvp'><a href='%1\$s'>%2\$s</a> is attending <a href='%3\$s'>%4\$s</a>.</span>");
            break;
        case 'N':
            // TRANS: HTML version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile URL, %2$s a profile name,
            // TRANS: %3$s is an event URL, %4$s an event title.
            $fmt = _m("<span class='automatic event-rsvp'><a href='%1\$s'>%2\$s</a> is not attending <a href='%3\$s'>%4\$s</a>.</span>");
            break;
        case '?':
            // TRANS: HTML version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile URL, %2$s a profile name,
            // TRANS: %3$s is an event URL, %4$s an event title.
            $fmt = _m("<span class='automatic event-rsvp'><a href='%1\$s'>%2\$s</a> might attend <a href='%3\$s'>%4\$s</a>.</span>");
            break;
        default:
            // TRANS: Exception thrown when requesting a user's RSVP status for a non-existing response code.
            // TRANS: %s is the non-existing response code.
            throw new Exception(sprintf(_m('Unknown response code %s.'),$response));
        }

        if (empty($event)) {
            $eventUrl = '#';
            // TRANS: Used as event title when not event title is available.
            // TRANS: Used as: Username [is [not ] attending|might attend] an unknown event.
            $eventTitle = _m('an unknown event');
        } else {
            $eventUrl = $event->getStored()->getUrl();
            $eventTitle = $event->title;
        }

        return sprintf($fmt,
                       htmlspecialchars($profile->getUrl()),
                       htmlspecialchars($profile->getBestName()),
                       htmlspecialchars($eventUrl),
                       htmlspecialchars($eventTitle));
    }

    static function toString($profile, $event, $response)
    {
        $fmt = null;

        switch ($response) {
        case 'Y':
            // TRANS: Plain text version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile name, %2$s is an event title.
            $fmt = _m('%1$s is attending %2$s.');
            break;
        case 'N':
            // TRANS: Plain text version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile name, %2$s is an event title.
            $fmt = _m('%1$s is not attending %2$s.');
            break;
        case '?':
            // TRANS: Plain text version of an RSVP ("please respond") status for a user.
            // TRANS: %1$s is a profile name, %2$s is an event title.
            $fmt = _m('%1$s might attend %2$s.');
            break;
        default:
            // TRANS: Exception thrown when requesting a user's RSVP status for a non-existing response code.
            // TRANS: %s is the non-existing response code.
            throw new Exception(sprintf(_m('Unknown response code %s.'),$response));
            break;
        }

        if (empty($event)) {
            // TRANS: Used as event title when not event title is available.
            // TRANS: Used as: Username [is [not ] attending|might attend] an unknown event.
            $eventTitle = _m('an unknown event');
        } else {
            $eventTitle = $event->title;
        }

        return sprintf($fmt,
                       $profile->getBestName(),
                       $eventTitle);
    }

    public function delete($useWhere=false)
    {
        self::blow('rsvp:for-event:%s', $this->id);
        return parent::delete($useWhere);
    }

    public function insert()
    {
        $result = parent::insert();
        if ($result === false) {
            common_log_db_error($this, 'INSERT', __FILE__);
            throw new ServerException(_('Failed to insert '._ve(get_called_class()).' into database'));
        }
        return $result;
    }
}

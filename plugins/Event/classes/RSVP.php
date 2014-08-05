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

if (!defined('STATUSNET')) {
    exit(1);
}

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
    public $uri;               // varchar(255)
    public $profile_id;        // int
    public $event_id;          // varchar(36) UUID
    public $response;            // tinyint
    public $created;           // datetime

    /**
     * Add the compound profile_id/event_id index to our cache keys
     * since the DB_DataObject stuff doesn't understand compound keys
     * except for the primary.
     *
     * @return array
     */
    function _allCacheKeys() {
        $keys = parent::_allCacheKeys();
        $keys[] = self::multicacheKey('RSVP', array('profile_id' => $this->profile_id,
                                                    'event_id' => $this->event_id));
        return $keys;
    }

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
                               'length' => 255,
                               'not null' => true),
                'profile_id' => array('type' => 'int'),
                'event_id' => array('type' => 'char',
                              'length' => 36,
                              'not null' => true,
                              'description' => 'UUID'),
                'response' => array('type' => 'char',
                                  'length' => '1',
                                  'description' => 'Y, N, or ? for three-state yes, no, maybe'),
                'created' => array('type' => 'datetime',
                                   'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'rsvp_uri_key' => array('uri'),
                'rsvp_profile_event_key' => array('profile_id', 'event_id'),
            ),
            'foreign keys' => array('rsvp_event_id_key' => array('event', array('event_id' => 'id')),
                                    'rsvp_profile_id__key' => array('profile', array('profile_id' => 'id'))),
            'indexes' => array('rsvp_created_idx' => array('created')),
        );
    }

    function saveNew($profile, $event, $verb, $options=array())
    {
        if (array_key_exists('uri', $options)) {
            $other = RSVP::getKV('uri', $options['uri']);
            if (!empty($other)) {
                // TRANS: Client exception thrown when trying to save an already existing RSVP ("please respond").
                throw new ClientException(_m('RSVP already exists.'));
            }
        }

        $other = RSVP::pkeyGet(array('profile_id' => $profile->id,
                                     'event_id' => $event->id));

        if (!empty($other)) {
            // TRANS: Client exception thrown when trying to save an already existing RSVP ("please respond").
            throw new ClientException(_m('RSVP already exists.'));
        }

        $rsvp = new RSVP();

        $rsvp->id          = UUID::gen();
        $rsvp->profile_id  = $profile->id;
        $rsvp->event_id    = $event->id;
        $rsvp->response      = self::codeFor($verb);

        if (array_key_exists('created', $options)) {
            $rsvp->created = $options['created'];
        } else {
            $rsvp->created = common_sql_now();
        }

        if (array_key_exists('uri', $options)) {
            $rsvp->uri = $options['uri'];
        } else {
            $rsvp->uri = common_local_url('showrsvp',
                                        array('id' => $rsvp->id));
        }

        $rsvp->insert();

        self::blow('rsvp:for-event:%s', $event->id);

        // XXX: come up with something sexier

        $content = $rsvp->asString();

        $rendered = $rsvp->asHTML();

        $options = array_merge(array('object_type' => $verb),
                               $options);

        if (!array_key_exists('uri', $options)) {
            $options['uri'] = $rsvp->uri;
        }

        $eventNotice = $event->getNotice();

        if (!empty($eventNotice)) {
            $options['reply_to'] = $eventNotice->id;
        }

        $saved = Notice::saveNew($profile->id,
                                 $content,
                                 array_key_exists('source', $options) ?
                                 $options['source'] : 'web',
                                 $options);

        return $saved;
    }

    function codeFor($verb)
    {
        switch ($verb) {
        case RSVP::POSITIVE:
            return 'Y';
            break;
        case RSVP::NEGATIVE:
            return 'N';
            break;
        case RSVP::POSSIBLE:
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
            return RSVP::POSITIVE;
            break;
        case 'N':
            return RSVP::NEGATIVE;
            break;
        case '?':
            return RSVP::POSSIBLE;
            break;
        default:
            // TRANS: Exception thrown when requesting an undefined code for RSVP.
            throw new Exception(sprintf(_m('Unknown code "%s".'),$code));
        }
    }

    function getNotice()
    {
        $notice = Notice::getKV('uri', $this->uri);
        if (empty($notice)) {
            // TRANS: Server exception thrown when requesting a non-exsting notice for an RSVP ("please respond").
            // TRANS: %s is the RSVP with the missing notice.
            throw new ServerException(sprintf(_m('RSVP %s does not correspond to a notice in the database.'),$this->id));
        }
        return $notice;
    }

    static function fromNotice(Notice $notice)
    {
        $rsvp = new RSVP();
        $rsvp->uri = $notice->uri;
        if (!$rsvp->find(true)) {
            throw new NoResultException($rsvp);
        }
        return $rsvp;
    }

    static function forEvent(Happening $event)
    {
        $keypart = sprintf('rsvp:for-event:%s', $event->id);

        $idstr = self::cacheGet($keypart);

        if ($idstr !== false) {
            $ids = explode(',', $idstr);
        } else {
            $ids = array();

            $rsvp = new RSVP();

            $rsvp->selectAdd();
            $rsvp->selectAdd('id');

            $rsvp->event_id = $event->id;

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
        $profile = Profile::getKV('id', $this->profile_id);
        if (empty($profile)) {
            // TRANS: Exception thrown when requesting a non-existing profile.
            // TRANS: %s is the ID of the non-existing profile.
            throw new Exception(sprintf(_m('No profile with ID %s.'),$this->profile_id));
        }
        return $profile;
    }

    function getEvent()
    {
        $event = Happening::getKV('id', $this->event_id);
        if (empty($event)) {
            // TRANS: Exception thrown when requesting a non-existing event.
            // TRANS: %s is the ID of the non-existing event.
            throw new Exception(sprintf(_m('No event with ID %s.'),$this->event_id));
        }
        return $event;
    }

    function asHTML()
    {
        $event = Happening::getKV('id', $this->event_id);

        return self::toHTML($this->getProfile(),
                            $event,
                            $this->response);
    }

    function asString()
    {
        $event = Happening::getKV('id', $this->event_id);

        return self::toString($this->getProfile(),
                              $event,
                              $this->response);
    }

    static function toHTML($profile, $event, $response)
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
            break;
        }

        if (empty($event)) {
            $eventUrl = '#';
            // TRANS: Used as event title when not event title is available.
            // TRANS: Used as: Username [is [not ] attending|might attend] an unknown event.
            $eventTitle = _m('an unknown event');
        } else {
            $notice = $event->getNotice();
            $eventUrl = $notice->getUrl();
            $eventTitle = $event->title;
        }

        return sprintf($fmt,
                       htmlspecialchars($profile->profileurl),
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
            $notice = $event->getNotice();
            $eventTitle = $event->title;
        }

        return sprintf($fmt,
                       $profile->getBestName(),
                       $eventTitle);
    }

    function delete($useWhere=false)
    {
        self::blow('rsvp:for-event:%s', $this->id);
        return parent::delete($useWhere);
    }
}

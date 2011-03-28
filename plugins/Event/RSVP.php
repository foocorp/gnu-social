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
     * Get an instance by key
     *
     * @param string $k Key to use to lookup (usually 'id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return RSVP object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('RSVP', $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * @param array $kv array of key-value mappings
     *
     * @return Bookmark object found, or null for no hits
     *
     */

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('RSVP', $kv);
    }

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
        common_debug("RSVP::saveNew({$profile->id}, {$event->id}, '$verb', 'some options');");

        if (array_key_exists('uri', $options)) {
            $other = RSVP::staticGet('uri', $options['uri']);
            if (!empty($other)) {
                throw new ClientException(_('RSVP already exists.'));
            }
        }

        $other = RSVP::pkeyGet(array('profile_id' => $profile->id,
                                     'event_id' => $event->id));

        if (!empty($other)) {
            throw new ClientException(_('RSVP already exists.'));
        }

        $rsvp = new RSVP();

        $rsvp->id          = UUID::gen();
        $rsvp->profile_id  = $profile->id;
        $rsvp->event_id    = $event->id;
        $rsvp->response      = self::codeFor($verb);

        common_debug("Got value {$rsvp->response} for verb {$verb}");

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
            throw new Exception("Unknown verb {$verb}");
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
            throw new Exception("Unknown code {$code}");
        }
    }

    function getNotice()
    {
        $notice = Notice::staticGet('uri', $this->uri);
        if (empty($notice)) {
            throw new ServerException("RSVP {$this->id} does not correspond to a notice in the DB.");
        }
        return $notice;
    }

    static function fromNotice($notice)
    {
        return RSVP::staticGet('uri', $notice->uri);
    }

    static function forEvent($event)
    {
        $rsvps = array(RSVP::POSITIVE => array(),
                       RSVP::NEGATIVE => array(),
                       RSVP::POSSIBLE => array());

        $rsvp = new RSVP();

        $rsvp->event_id = $event->id;

        if ($rsvp->find()) {
            while ($rsvp->fetch()) {
                $verb = self::verbFor($rsvp->response);
                $rsvps[$verb][] = clone($rsvp);
            }
        }

        return $rsvps;
    }

    function getProfile()
    {
        $profile = Profile::staticGet('id', $this->profile_id);
        if (empty($profile)) {
            throw new Exception("No profile with ID {$this->profile_id}");
        }
        return $profile;
    }

    function getEvent()
    {
        $event = Happening::staticGet('id', $this->event_id);
        if (empty($event)) {
            throw new Exception("No event with ID {$this->event_id}");
        }
        return $event;
    }

    function asHTML()
    {
        $event = Happening::staticGet('id', $this->event_id);

        return self::toHTML($this->getProfile(),
                            $event,
                            $this->response);
    }

    function asString()
    {
        $event = Happening::staticGet('id', $this->event_id);

        return self::toString($this->getProfile(),
                              $event,
                              $this->response);
    }

    static function toHTML($profile, $event, $response)
    {
        $fmt = null;

        switch ($response) {
        case 'Y':
            $fmt = _("<span class='automatic event-rsvp'><a href='%1s'>%2s</a> is attending <a href='%3s'>%4s</a>.</span>");
            break;
        case 'N':
            $fmt = _("<span class='automatic event-rsvp'><a href='%1s'>%2s</a> is not attending <a href='%3s'>%4s</a>.</span>");
            break;
        case '?':
            $fmt = _("<span class='automatic event-rsvp'><a href='%1s'>%2s</a> might attend <a href='%3s'>%4s</a>.</span>");
            break;
        default:
            throw new Exception("Unknown response code {$response}");
            break;
        }

        if (empty($event)) {
            $eventUrl = '#';
            $eventTitle = _('an unknown event');
        } else {
            $notice = $event->getNotice();
            $eventUrl = $notice->bestUrl();
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
            $fmt = _("%1s is attending %2s.");
            break;
        case 'N':
            $fmt = _("%1s is not attending %2s.");
            break;
        case '?':
            $fmt = _("%1s might attend %2s.>");
            break;
        default:
            throw new Exception("Unknown response code {$response}");
            break;
        }

        if (empty($event)) {
            $eventTitle = _('an unknown event');
        } else {
            $notice = $event->getNotice();
            $eventTitle = $event->title;
        }

        return sprintf($fmt,
                       $profile->getBestName(),
                       $eventTitle);
    }
}

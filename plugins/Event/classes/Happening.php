<?php
/**
 * Data class for happenings
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
 * Data class for happenings
 *
 * There's already an Event class in lib/event.php, so we couldn't
 * call this an Event without causing a hole in space-time.
 *
 * "Happening" seemed good enough.
 *
 * @category Event
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      Managed_DataObject
 */
class Happening extends Managed_DataObject
{
    const OBJECT_TYPE = 'http://activitystrea.ms/schema/1.0/event';

    public $__table = 'happening'; // table name
    public $id;                    // varchar(36) UUID
    public $uri;                   // varchar(191)   not 255 because utf8mb4 takes more space
    public $profile_id;            // int
    public $start_time;            // datetime
    public $end_time;              // datetime
    public $title;                 // varchar(191)   not 255 because utf8mb4 takes more space
    public $location;              // varchar(191)   not 255 because utf8mb4 takes more space
    public $url;                   // varchar(191)   not 255 because utf8mb4 takes more space
    public $description;           // text
    public $created;               // datetime

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'A real-world happening',
            'fields' => array(
                'id' => array('type' => 'char',
                              'length' => 36,
                              'not null' => true,
                              'description' => 'UUID'),
                'uri' => array('type' => 'varchar',
                               'length' => 191,
                               'not null' => true),
                'profile_id' => array('type' => 'int', 'not null' => true),
                'start_time' => array('type' => 'datetime', 'not null' => true),
                'end_time' => array('type' => 'datetime', 'not null' => true),
                'title' => array('type' => 'varchar',
                                 'length' => 191,
                                 'not null' => true),
                'location' => array('type' => 'varchar',
                                    'length' => 191),
                'url' => array('type' => 'varchar',
                               'length' => 191),
                'description' => array('type' => 'text'),
                'created' => array('type' => 'datetime',
                                   'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'happening_uri_key' => array('uri'),
            ),
            'foreign keys' => array('happening_profile_id__key' => array('profile', array('profile_id' => 'id')),
                                    'happening_uri__key' => array('notice', array('uri' => 'uri'))),
            'indexes' => array('happening_created_idx' => array('created'),
                               'happening_start_end_idx' => array('start_time', 'end_time')),
        );
    }

    public static function saveActivityObject(Activity $act, Notice $stored)
    {
        if (count($act->objects) !== 1) {
            // TRANS: Exception thrown when there are too many activity objects.
            throw new Exception(_m('Too many activity objects.'));
        }
        $actobj = $act->objects[0];
        if (!ActivityUtils::compareTypes($actobj->type, [Happening::OBJECT_TYPE])) {
            // TRANS: Exception thrown when event plugin comes across a non-event type object.
            throw new Exception(_m('Wrong type for object.'));
        }

        try {
            $other = Happening::getByKeys(['uri' => $actobj->id]);
            throw AlreadyFulfilledException('Happening already exists.');
        } catch (NoResultException $e) {
            // alright, let's save this
        }

        $dtstart = null;
        $dtend = null;
        $location = null;
        $url = null;

        foreach ($actobj->extra as $extra) {
            switch ($extra[0]) {
            case 'dtstart':
                $dtstart = $extra[2];
            case 'dtend':
                $dtend = $extra[2];
                break;
            case 'location':
                // location is optional
                $location = $extra[2];
                break;
            case 'url':
                // url is optional
                $url = $extra[2];
            }
        }
        if(empty($dtstart)) {
            // TRANS: Exception thrown when has no start date
            throw new Exception(_m('No start date for event.'));
        }
        if(empty($dtend)) {
            // TRANS: Exception thrown when has no end date
            throw new Exception(_m('No end date for event.'));
        }

        // convert RFC3339 dates delivered in Activity Stream to MySQL DATETIME date format
        $start_time = new DateTime($dtstart);
        $start_time->setTimezone(new DateTimeZone('UTC'));
        $start_time = $start_time->format('Y-m-d H:i:s');
        $end_time = new DateTime($dtend);
        $end_time->setTimezone(new DateTimeZone('UTC'));
        $end_time = $end_time->format('Y-m-d H:i:s');

        $ev = new Happening();

        $ev->id          = UUID::gen();
        $ev->uri         = $actobj->id;
        $ev->profile_id  = $stored->getProfile()->getID();
        $ev->start_time  = $start_time;
        $ev->end_time    = $end_time;
        $ev->title       = $actobj->title;
        $ev->location    = $location;
        $ev->description = $stored->getContent();
        $ev->url         = $url;
        $ev->created     = $stored->getCreated();

        $ev->insert();
        return $ev;
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

    /**
     * Returns the profile's canonical url, not necessarily a uri/unique id
     *
     * @return string $url
     */
    public function getUrl()
    {
        if (empty($this->url) ||
                !filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($this->url);
        }
        return $this->url;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getStored()
    {
        return Notice::getByKeys(array('uri'=>$this->getUri()));
    }

    static function fromStored(Notice $stored)
    {
        if (!ActivityUtils::compareTypes($stored->getObjectType(), [self::OBJECT_TYPE])) {
            throw new ServerException('Notice is not of type '.self::OBJECT_TYPE);
        }
        return self::getByKeys(array('uri'=>$stored->getUri()));
    }

    function getRSVPs()
    {
        return RSVP::forEvent($this);
    }

    function getRSVP($profile)
    {
        return RSVP::pkeyGet(array('profile_id' => $profile->getID(),
                                   'event_uri' => $this->getUri()));
    }

    static public function getObjectType()
    {
        return self::OBJECT_TYPE;
    }

    public function asActivityObject()
    {
        $actobj = new ActivityObject();
        $actobj->id = $this->getUri();
        $actobj->type = self::getObjectType();
        $actobj->title = $this->title;
        $actobj->summary = $this->description;
        $actobj->extra[] = array('dtstart',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                common_date_iso8601($this->start_time));
        $actobj->extra[] = array('dtend',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                common_date_iso8601($this->end_time));
        $actobj->extra[] = array('location',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                $this->location);
        try {
            $actobj->extra[] = array('url',
                                    array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                    $this->getUrl());
        } catch (InvalidUrlException $e) {
            // oh well, no URL for you!
        }

        /* We don't use these ourselves, but we add them to be nice RSS/XML citizens */
        $actobj->extra[] = array('startdate',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                common_date_iso8601($this->start_time));
        $actobj->extra[] = array('enddate',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                common_date_iso8601($this->end_time));
        $actobj->extra[] = array('location',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                $this->location);

        return $actobj;
    }
}

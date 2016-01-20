<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Add a new event
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Add a new event
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NeweventAction extends FormAction
{
    protected $form = 'Event';

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Title for new event form.
        return _m('TITLE','New event');
    }

    protected function doPost()
    {
        // HUMAN TEXT DATA
        $title = $this->trimmed('title');
        if (empty($title)) {
            // TRANS: Client exception thrown when trying to post an event without providing a title.
            throw new ClientException(_m('Event must have a title.'));
        }
        $description = $this->trimmed('description');


        // TIME PARSING
        $tz          = $this->trimmed('tz');

        $startDate = $this->trimmed('startdate');
        if (empty($startDate)) {
            // TRANS: Client exception thrown when trying to post an event without providing a start date.
            throw new ClientException(_m('Start date required.'));
        }
        $startTime = $this->trimmed('event-starttime');
        if (empty($startTime)) {
            // TRANS: Client exception thrown when trying to post an event without providing a start time.
            throw new ClientException(_m('Event must have a start time.'));
        }
        $start_str = sprintf('%s %s %s', $startDate, $startTime, $tz);
        $start = strtotime($start_str);
        if ($start === false) {
            // TRANS: Client exception thrown when trying to post an event with a date that cannot be processed.
            // TRANS: %s is the data that could not be processed.
            throw new ClientException(sprintf(_m('Could not parse date %s.'), _ve($start_str)));
        }

        $endDate = $this->trimmed('enddate');
        if (empty($endDate)) {
            // TRANS: Client exception thrown when trying to post an event without providing an end date.
            throw new ClientException(_m('End date required.'));
        }
        $endTime = $this->trimmed('event-endtime');
        if (empty($endTime)) {
            // TRANS: Client exception thrown when trying to post an event without providing an end time.
            throw new ClientException(_m('Event must have an end time.'));
        }
        $end_str   = sprintf('%s %s %s', $endDate, $endTime, $tz);
        $end = strtotime($end_str);
        if ($end === false) {
            // TRANS: Client exception thrown when trying to post an event with a date that cannot be processed.
            // TRANS: %s is the data that could not be processed.
            throw new ClientException(sprintf(_m('Could not parse date %s.'), _ve($end_str)));
        }

        $url         = $this->trimmed('url');
        if (!empty($url) && !common_valid_http_url($url)) {
            // TRANS: Client exception thrown when trying to post an event with an invalid (non-empty) URL.
            throw new ClientException(_m('An event URL must be a valid HTTP/HTTPS link.'));
        }


        // LOCATION DATA
        $location    = $this->trimmed('location');



        $options = [ 'source' => 'web' ];

        $act = new Activity();
        $act->verb = ActivityVerb::POST;
        $act->time = time();
        $act->actor = $this->scoped->asActivityObject();

        $act->context = new ActivityContext();
        // FIXME: Add location here? Let's make it possible to include current location with less code...


        $actobj = new ActivityObject();
        $actobj->id = UUID::gen();
        $actobj->type = Happening::OBJECT_TYPE;
        $actobj->title = $title;
        $actobj->summary = $description;
        $actobj->extra[] = array('dtstart',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                common_date_iso8601($start_str));
        $actobj->extra[] = array('dtend',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                common_date_iso8601($end_str));
        $actobj->extra[] = array('location',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                $location);
        $actobj->extra[] = array('url',
                                array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                                $url);

        /* We don't use these ourselves, but we add them to be nice RSS/XML citizens */
        $actobj->extra[] = array('startdate',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                common_date_iso8601($start_str));
        $actobj->extra[] = array('enddate',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                common_date_iso8601($end_str));
        $actobj->extra[] = array('location',
                                array('xmlns' => 'http://purl.org/rss/1.0/modules/event/'),
                                $location);

        $act->objects = array($actobj);

        $stored = Notice::saveActivity($act, $this->scoped, $options);

        return _m('Saved the event.');
    }
}

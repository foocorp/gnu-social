<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Microapp plugin for event invitations and RSVPs
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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Event plugin
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EventPlugin extends MicroAppPlugin
{
    /**
     * Set up our tables (event and rsvp)
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('happening', Happening::schemaDef());
        $schema->ensureTable('rsvp', RSVP::schemaDef());

        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        $m->connect('main/event/new',
                    array('action' => 'newevent'));
        $m->connect('main/event/rsvp',
                    array('action' => 'newrsvp'));
        $m->connect('main/event/rsvp/cancel',
                    array('action' => 'cancelrsvp'));
        $m->connect('event/:id',
                    array('action' => 'showevent'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        $m->connect('rsvp/:id',
                    array('action' => 'showrsvp'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        $m->connect('main/event/updatetimes',
                    array('action' => 'timelist'));
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Event',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Event',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Event invitations and RSVPs.'));
        return true;
    }

    function appTitle() {
        // TRANS: Title for event application.
        return _m('TITLE','Event');
    }

    function tag() {
        return 'event';
    }

    function types() {
        return array(Happening::OBJECT_TYPE,
                     RSVP::POSITIVE,
                     RSVP::NEGATIVE,
                     RSVP::POSSIBLE);
    }

    /**
     * Given a parsed ActivityStreams activity, save it into a notice
     * and other data structures.
     *
     * @param Activity $activity
     * @param Profile $actor
     * @param array $options=array()
     *
     * @return Notice the resulting notice
     */
    function saveNoticeFromActivity(Activity $activity, Profile $actor, array $options=array())
    {
        if (count($activity->objects) != 1) {
            // TRANS: Exception thrown when there are too many activity objects.
            throw new Exception(_m('Too many activity objects.'));
        }

        $happeningObj = $activity->objects[0];

        if ($happeningObj->type != Happening::OBJECT_TYPE) {
            // TRANS: Exception thrown when event plugin comes across a non-event type object.
            throw new Exception(_m('Wrong type for object.'));
        }

        $notice = null;

        switch ($activity->verb) {
        case ActivityVerb::POST:
        	// FIXME: get startTime, endTime, location and URL
            $notice = Happening::saveNew($actor,
                                         $start_time,
                                         $end_time,
                                         $happeningObj->title,
                                         null,
                                         $happeningObj->summary,
                                         null,
                                         $options);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $happening = Happening::getKV('uri', $happeningObj->id);
            if (empty($happening)) {
                // FIXME: save the event
                // TRANS: Exception thrown when trying to RSVP for an unknown event.
                throw new Exception(_m('RSVP for unknown event.'));
            }
            $notice = RSVP::saveNew($actor, $happening, $activity->verb, $options);
            break;
        default:
            // TRANS: Exception thrown when event plugin comes across a undefined verb.
            throw new Exception(_m('Unknown verb for events.'));
        }

        return $notice;
    }

    /**
     * Turn a Notice into an activity object
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */
    function activityObjectFromNotice(Notice $notice)
    {
        $happening = null;

        switch ($notice->object_type) {
        case Happening::OBJECT_TYPE:
            $happening = Happening::fromNotice($notice);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $rsvp  = RSVP::fromNotice($notice);
            $happening = $rsvp->getEvent();
            break;
        }

        if (empty($happening)) {
            // TRANS: Exception thrown when event plugin comes across a unknown object type.
            throw new Exception(_m('Unknown object type.'));
        }

        $notice = $happening->getNotice();

        if (empty($notice)) {
            // TRANS: Exception thrown when referring to a notice that is not an event an in event context.
            throw new Exception(_m('Unknown event notice.'));
        }

        $obj = new ActivityObject();

        $obj->id      = $happening->uri;
        $obj->type    = Happening::OBJECT_TYPE;
        $obj->title   = $happening->title;
        $obj->summary = $happening->description;
        $obj->link    = $notice->getUrl();

        // XXX: how to get this stuff into JSON?!

        $obj->extra[] = array('dtstart',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->start_time));

        $obj->extra[] = array('dtend',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->end_time));

		// FIXME: add location
		// FIXME: add URL
		
        // XXX: probably need other stuff here

        return $obj;
    }

    /**
     * Change the verb on RSVP notices
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */
    protected function extendActivity(Notice $stored, Activity $act, Profile $scoped=null) {
        switch ($stored->object_type) {
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $act->verb = $stored->object_type;
            break;
        }
        return true;
    }

    function adaptNoticeListItem($nli)
    {
        $notice = $nli->notice;

        switch ($notice->object_type) {
        case Happening::OBJECT_TYPE:
            return new EventListItem($nli);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            return new RSVPListItem($nli);
            break;
        }
        return null;
    }


    /**
     * Form for our app
     *
     * @param HTMLOutputter $out
     * @return Widget
     */
    function entryForm($out)
    {
        return new EventForm($out);
    }

    /**
     * When a notice is deleted, clean up related tables.
     *
     * @param Notice $notice
     */
    function deleteRelated(Notice $notice)
    {
        switch ($notice->object_type) {
        case Happening::OBJECT_TYPE:
            common_log(LOG_DEBUG, "Deleting event from notice...");
            $happening = Happening::fromNotice($notice);
            $happening->delete();
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            common_log(LOG_DEBUG, "Deleting rsvp from notice...");
            $rsvp = RSVP::fromNotice($notice);
            common_log(LOG_DEBUG, "to delete: $rsvp->id");
            $rsvp->delete();
            break;
        default:
            common_log(LOG_DEBUG, "Not deleting related, wtf...");
        }
    }

    function onEndShowScripts($action)
    {
        $action->script($this->path('js/event.js'));
    }

    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('css/event.css'));
        return true;
    }

    function onStartAddNoticeReply($nli, $parent, $child)
    {
        // Filter out any poll responses
        if (($parent->object_type == Happening::OBJECT_TYPE) &&
            in_array($child->object_type, array(RSVP::POSITIVE, RSVP::NEGATIVE, RSVP::POSSIBLE))) {
            return false;
        }
        return true;
    }
}

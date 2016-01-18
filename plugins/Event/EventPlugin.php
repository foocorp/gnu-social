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

if (!defined('GNUSOCIAL')) { exit(1); }

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
class EventPlugin extends ActivityVerbHandlerPlugin
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

    public function onBeforePluginCheckSchema()
    {
        RSVP::beforeSchemaUpdate();
        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
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

        $m->connect(':nickname/events',
                    array('action' => 'events'),
                    array('nickname' => Nickname::DISPLAY_FMT));
        return true;
    }

    function onPluginVersion(array &$versions)
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
        return array(Happening::OBJECT_TYPE);
    }

    function verbs() {
        return array(ActivityVerb::POST,
                     RSVP::POSITIVE,
                     RSVP::NEGATIVE,
                     RSVP::POSSIBLE);
    }

    public function newFormAction() {
        // such as 'newbookmark' or 'newevent' route
        return 'new'.$this->tag();
    }

    function onStartShowEntryForms(&$tabs)
    {
        $tabs[$this->tag()] = array('title' => $this->appTitle(),
                                    'href'  => common_local_url($this->newFormAction()),
                                   );
        return true;
    }

    function onStartMakeEntryForm($tag, $out, &$form)
    {
        if ($tag == $this->tag()) {
            $form = $this->entryForm($out);
            return false;
        }

        return true;
    }

    protected function getActionTitle(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        return $verb;
    }

    protected function doActionPreparation(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        return true;
    }

    protected function doActionPost(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        throw new ServerException('Event does not handle doActionPost yet', 501);
    }

    protected function getActivityForm(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        return new RSVPForm(Happening::fromStored($target), $action);
    }

    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())
    {
        if (count($act->objects) !== 1) {
            // TRANS: Exception thrown when there are too many activity objects.
            throw new Exception(_m('Too many activity objects.'));
        }
        $actobj = $act->objects[0];

        switch ($act->verb) {
        case ActivityVerb::POST:
            if (!ActivityUtils::compareTypes($actobj->type, array(Happening::OBJECT_TYPE))) {
                // TRANS: Exception thrown when event plugin comes across a non-event type object.
                throw new Exception(_m('Wrong type for object.'));
            }
            return Happening::saveActivityObject($actobj, $stored);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $happening = Happening::getKV('uri', $actobj->id);
            if (empty($happening)) {
                // FIXME: save the event
                // TRANS: Exception thrown when trying to RSVP for an unknown event.
                throw new Exception(_m('RSVP for unknown event.'));
            }
            $object = RSVP::saveNewFromNotice($stored, $happening, $act->verb);
            // Our data model expects this
            $stored->object_type = $act->verb;
            return $object;
            break;
        default:
            common_log(LOG_ERR, 'Unknown verb for events.');
            return NULL;
        }
    }

    function activityObjectFromNotice(Notice $stored)
    {
        $happening = null;

        switch (true) {
        case ActivityUtils::compareVerbs($stored->verb, array(ActivityVerb::POST)) &&
                ActivityUtils::compareTypes($stored->object_type, array(Happening::OBJECT_TYPE)):
            $happening = Happening::fromStored($stored);
            break;
        // FIXME: Why are these object_type??
        case ActivityUtils::compareTypes($stored->object_type, array(RSVP::POSITIVE, RSVP::NEGATIVE, RSVP::POSSIBLE)):
            $rsvp  = RSVP::fromNotice($stored);
            $happening = $rsvp->getEvent();
            break;
        default:
            // TRANS: Exception thrown when event plugin comes across a unknown object type.
            throw new Exception(_m('Unknown object type.'));
        }

        $obj = new ActivityObject();

        $obj->id      = $happening->getUri();
        $obj->type    = Happening::OBJECT_TYPE;
        $obj->title   = $happening->title;
        $obj->summary = $happening->description;
        $obj->link    = $happening->getStored()->getUrl();

        $obj->extra[] = array('dtstart',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->start_time));
        $obj->extra[] = array('dtend',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->end_time));
        $obj->extra[] = array('location', false, $happening->location);
        $obj->extra[] = array('url', false, $happening->url);

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
        switch (true) {
        // FIXME: Why are these object_type??
        case ActivityUtils::compareTypes($stored->object_type, array(RSVP::POSITIVE, RSVP::NEGATIVE, RSVP::POSSIBLE)):
            $act->verb = $stored->object_type;
            break;
        }
        return true;
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
            try {
                $happening = Happening::fromStored($notice);
                $happening->delete();
            } catch (NoResultException $e) {
                // already gone
            }
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
        if (($parent->object_type == Happening::OBJECT_TYPE) &&
            in_array($child->object_type, array(RSVP::POSITIVE, RSVP::NEGATIVE, RSVP::POSSIBLE))) {
            return false;
        }
        return true;
    }

    protected function showNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        switch (true) {
        case ActivityUtils::compareTypes($stored->verb, array(ActivityVerb::POST)) &&
                ActivityUtils::compareTypes($stored->object_type, array(Happening::OBJECT_TYPE)):
            $this->showEvent($stored, $out, $scoped);
            break;
        case ActivityUtils::compareVerbs($stored->verb, array(RSVP::POSITIVE, RSVP::NEGATIVE, RSVP::POSSIBLE)):
            $this->showRSVP($stored, $out, $scoped);
            break;
        default:
            throw new ServerException('This is not an Event notice');
        }
        return true;
    }

    protected function showEvent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        common_debug('shownotice'.$stored->getID());
        $profile = $stored->getProfile();
        $event   = Happening::fromStored($stored);

        $out->elementStart('div', 'h-event');

        $out->elementStart('h3', 'p-summary p-name');

        try {
            $out->element('a', array('href' => $event->getUrl()), $event->title);
        } catch (InvalidUrlException $e) {
            $out->text($event->title);
        }

        $out->elementEnd('h3');

        $now       = new DateTime();
        $startDate = new DateTime($event->start_time);
        $endDate   = new DateTime($event->end_time);
        $userTz    = new DateTimeZone(common_timezone());

        // Localize the time for the observer
        $now->setTimeZone($userTz);
        $startDate->setTimezone($userTz);
        $endDate->setTimezone($userTz);

        $thisYear  = $now->format('Y');
        $startYear = $startDate->format('Y');
        $endYear   = $endDate->format('Y');

        $dateFmt = 'D, F j, '; // e.g.: Mon, Aug 31

        if ($startYear != $thisYear || $endYear != $thisYear) {
            $dateFmt .= 'Y,'; // append year if we need to think about years
        }

        $startDateStr = $startDate->format($dateFmt);
        $endDateStr = $endDate->format($dateFmt);

        $timeFmt = 'g:ia';

        $startTimeStr = $startDate->format($timeFmt);
        $endTimeStr = $endDate->format("{$timeFmt} (T)");

        $out->elementStart('div', 'event-times'); // VEVENT/EVENT-TIMES IN

        // TRANS: Field label for event description.
        $out->element('strong', null, _m('Time:'));

        $out->element('time', array('class' => 'dt-start',
                                    'datetime' => common_date_iso8601($event->start_time)),
                      $startDateStr . ' ' . $startTimeStr);
        $out->text(' – ');
        $out->element('time', array('class' => 'dt-end',
                                    'datetime' => common_date_iso8601($event->end_time)),
                      $startDateStr != $endDateStr
                                    ? "$endDateStr $endTimeStr"
                                    :  $endTimeStr);

        $out->elementEnd('div'); // VEVENT/EVENT-TIMES OUT

        if (!empty($event->location)) {
            $out->elementStart('div', 'event-location');
            // TRANS: Field label for event description.
            $out->element('strong', null, _m('Location:'));
            $out->element('span', 'p-location', $event->location);
            $out->elementEnd('div');
        }

        if (!empty($event->description)) {
            $out->elementStart('div', 'event-description');
            // TRANS: Field label for event description.
            $out->element('strong', null, _m('Description:'));
            $out->element('div', 'p-description', $event->description);
            $out->elementEnd('div');
        }

        $rsvps = $event->getRSVPs();

        $out->elementStart('div', 'event-rsvps');

        // TRANS: Field label for event description.
        $out->element('strong', null, _m('Attending:'));
        $out->elementStart('ul', 'attending-list');

        foreach ($rsvps as $verb => $responses) {
            $out->elementStart('li', 'rsvp-list');
            switch ($verb) {
            case RSVP::POSITIVE:
                $out->text(_('Yes:'));
                break;
            case RSVP::NEGATIVE:
                $out->text(_('No:'));
                break;
            case RSVP::POSSIBLE:
                $out->text(_('Maybe:'));
                break;
            }
            $ids = array();
            foreach ($responses as $response) {
                $ids[] = $response->profile_id;
            }
            $ids = array_slice($ids, 0, ProfileMiniList::MAX_PROFILES + 1);
            $minilist = new ProfileMiniList(Profile::multiGet('id', $ids), $out);
            $minilist->show();

            $out->elementEnd('li');
        }

        $out->elementEnd('ul');
        $out->elementEnd('div');

        if ($scoped instanceof Profile) {
            $rsvp = $event->getRSVP($scoped);

            if (empty($rsvp)) {
                $form = new RSVPForm($event, $out);
            } else {
                $form = new CancelRSVPForm($rsvp, $out);
            }

            $form->show();
        }
        $out->elementEnd('div');
    }

    protected function showRSVP(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        $rsvp = RSVP::fromNotice($stored);

        if (empty($rsvp)) {
            // TRANS: Content for a deleted RSVP list item (RSVP stands for "please respond").
            $out->element('p', null, _m('Deleted.'));
            return;
        }

        $out->elementStart('div', 'rsvp');
        $out->raw($rsvp->asHTML());
        $out->elementEnd('div');
    }

    function onEndPersonalGroupNav(Menu $menu, Profile $target, Profile $scoped=null)
    {
        $menu->menuItem(common_local_url('events', array('nickname' => $target->getNickname())),
                          // TRANS: Menu item in sample plugin.
                          _m('Happenings'),
                          // TRANS: Menu item title in sample plugin.
                          _m('A list of your events'), false, 'nav_timeline_events');
        return true;
    }
}

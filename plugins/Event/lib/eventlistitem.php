<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Notice-list representation of an event
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
 * Notice-list representation of an event
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EventListItem extends NoticeListItemAdapter
{
    function showNotice()
    {
        $this->nli->showAuthor();
        $this->showContent();
    }

    function showContent()
    {
        $notice = $this->nli->notice;
        $out    = $this->nli->out;

        $profile = $notice->getProfile();
        $event   = Happening::fromNotice($notice);

        if (empty($event)) {
            // TRANS: Content for a deleted RSVP list item (RSVP stands for "please respond").
            $out->element('p', null, _m('Deleted.'));
            return;
        }

        // e-content since we're part of a h-entry
        $out->elementStart('div', 'h-event e-content'); // VEVENT IN

        $out->elementStart('h3', 'p-summary p-name');  // VEVENT/H3 IN

        try {
            $out->element('a', array('href' => $event->getUrl()), $event->title);
        } catch (InvalidUrlException $e) {
            $out->text($event->title);
        }

        $out->elementEnd('h3'); // VEVENT/H3 OUT

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
                      $startDateStr == $endDateStr
                                    ? "$endDatestr $endTimeStr"
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

        $user = common_current_user();

        if (!empty($user)) {
            $rsvp = $event->getRSVP($user->getProfile());

            if (empty($rsvp)) {
                $form = new RSVPForm($event, $out);
            } else {
                $form = new CancelRSVPForm($rsvp, $out);
            }

            $form->show();
        }

        $out->elementEnd('div'); // vevent out
    }
}

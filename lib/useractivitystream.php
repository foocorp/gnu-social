<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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
 */

/**
 * Class for activity streams
 *
 * Includes faves, notices, and subscriptions.
 *
 * We extend atomusernoticefeed since it does some nice setup for us.
 *
 */
class UserActivityStream extends AtomUserNoticeFeed
{
    public $activities = array();

    const OUTPUT_STRING = 1;
    const OUTPUT_RAW = 2;
    public $outputMode = self::OUTPUT_STRING;

    /**
     *
     * @param User $user
     * @param boolean $indent
     * @param boolean $outputMode: UserActivityStream::OUTPUT_STRING to return a string,
     *                           or UserActivityStream::OUTPUT_RAW to go to raw output.
     *                           Raw output mode will attempt to stream, keeping less
     *                           data in memory but will leave $this->activities incomplete.
     */
    function __construct($user, $indent = true, $outputMode = UserActivityStream::OUTPUT_STRING)
    {
        parent::__construct($user, null, $indent);

        $this->outputMode = $outputMode;
        if ($this->outputMode == self::OUTPUT_STRING) {
            // String buffering? Grab all the notices now.
            $notices = $this->getNotices();
        } elseif ($this->outputMode == self::OUTPUT_RAW) {
            // Raw output... need to restructure from the stringer init.
            $this->xw = new XMLWriter();
            $this->xw->openURI('php://output');
            if(is_null($indent)) {
                $indent = common_config('site', 'indent');
            }
            $this->xw->setIndent($indent);

            // We'll fetch notices later.
            $notices = array();
        } else {
            throw new Exception('Invalid outputMode provided to ' . __METHOD__);
        }

        // Assume that everything but notices is feasible
        // to pull at once and work with in memory...
        $subscriptions = $this->getSubscriptions();
        $subscribers   = $this->getSubscribers();
        $groups        = $this->getGroups();
        $faves         = $this->getFaves();

        $objs = array_merge($subscriptions, $subscribers, $groups, $faves, $notices);

        // Sort by create date

        usort($objs, 'UserActivityStream::compareObject');

        // We'll keep these around for later, and interleave them into
        // the output stream with the user's notices.
        foreach ($objs as $obj) {
            $this->activities[] = $obj->asActivity();
        }
    }

    /**
     * Interleave the pre-sorted subs/groups/faves with the user's
     * notices, all in reverse chron order.
     */
    function renderEntries()
    {
        $end = time() + 1;
        foreach ($this->activities as $act) {
            $start = $act->time;

            if ($this->outputMode == self::OUTPUT_RAW && $start != $end) {
                // In raw mode, we haven't pre-fetched notices.
                // Grab the chunks of notices between other activities.
                $notices = $this->getNoticesBetween($start, $end);
                foreach ($notices as $noticeAct) {
                    $noticeAct->asActivity()->outputTo($this, false, false);
                }
            }

            // Only show the author sub-element if it's different from default user
            $act->outputTo($this, false, ($act->actor->id != $this->user->uri));

            $end = $start;
        }

        if ($this->outputMode == self::OUTPUT_RAW) {
            // Grab anything after the last pre-sorted activity.
            $notices = $this->getNoticesBetween(0, $end);
            foreach ($notices as $noticeAct) {
                $noticeAct->asActivity()->outputTo($this, false, false);
            }
        }
    }

    function compareObject($a, $b)
    {
        $ac = strtotime((empty($a->created)) ? $a->modified : $a->created);
        $bc = strtotime((empty($b->created)) ? $b->modified : $b->created);

        return (($ac == $bc) ? 0 : (($ac < $bc) ? 1 : -1));
    }

    function getSubscriptions()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscriber = $this->user->id;

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscribed != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    function getSubscribers()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscribed = $this->user->id;

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscriber != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    function getFaves()
    {
        $faves = array();

        $fave = new Fave();

        $fave->user_id = $this->user->id;

        if ($fave->find()) {
            while ($fave->fetch()) {
                $faves[] = clone($fave);
            }
        }

        return $faves;
    }

    /**
     *
     * @param int $start unix timestamp for earliest
     * @param int $end unix timestamp for latest
     * @return array of Notice objects
     */
    function getNoticesBetween($start=0, $end=0)
    {
        $notices = array();

        $notice = new Notice();

        $notice->profile_id = $this->user->id;

        if ($start) {
            $tsstart = common_sql_date($start);
            $notice->whereAdd("created >= '$tsstart'");
        }
        if ($end) {
            $tsend = common_sql_date($end);
            $notice->whereAdd("created < '$tsend'");
        }

        $notice->orderBy('created DESC');

        if ($notice->find()) {
            while ($notice->fetch()) {
                $notices[] = clone($notice);
            }
        }

        return $notices;
    }

    function getNotices()
    {
        return $this->getNoticesBetween();
    }

    function getGroups()
    {
        $groups = array();

        $gm = new Group_member();

        $gm->profile_id = $this->user->id;

        if ($gm->find()) {
            while ($gm->fetch()) {
                $groups[] = clone($gm);
            }
        }

        return $groups;
    }
}

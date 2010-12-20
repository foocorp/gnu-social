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
    function __construct($user, $indent = true)
    {
        parent::__construct($user, null, $indent);

        $subscriptions = $this->getSubscriptions();
        $subscribers   = $this->getSubscribers();
        $groups        = $this->getGroups();
        $faves         = $this->getFaves();
        $notices       = $this->getNotices();

        $objs = array_merge($subscriptions, $subscribers, $groups, $faves, $notices);

        // Sort by create date

        usort($objs, 'UserActivityStream::compareObject');

        foreach ($objs as $obj) {
            $act = $obj->asActivity();
            // Only show the author sub-element if it's different from default user
            $str = $act->asString(false, ($act->actor->id != $this->user->uri));
            $this->addEntryRaw($str);
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

    function getNotices()
    {
        $notices = array();

        $notice = new Notice();

        $notice->profile_id = $this->user->id;

        if ($notice->find()) {
            while ($notice->fetch()) {
                $notices[] = clone($notice);
            }
        }

        return $notices;
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

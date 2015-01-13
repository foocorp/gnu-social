<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @package OStatusPlugin
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class PeopletagsalmonAction extends SalmonAction
{
    var $peopletag = null;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            // TRANS: Client error displayed trying to perform an action without providing an ID.
            $this->clientError(_m('No ID.'));
        }

        $this->peopletag = Profile_list::getKV('id', $id);

        if (!$this->peopletag instanceof Profile_list) {
            // TRANS: Client error displayed when referring to a non-existing list.
            $this->clientError(_m('No such list.'));
        }

        $this->target = $this->peopletag;

        $remote_list = Ostatus_profile::getKV('peopletag_id', $id);

        if ($remote_list instanceof Ostatus_profile) {
            // TRANS: Client error displayed when trying to send a message to a remote list.
            $this->clientError(_m('Cannot accept remote posts for a remote list.'));
        }

        return true;
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */

    /**
     * Postel's law: consider a "follow" notification as a "join".
     */
    function handleFollow()
    {
        $this->handleSubscribe();
    }

    /**
     * Postel's law: consider an "unfollow" notification as a "unsubscribe".
     */
    function handleUnfollow()
    {
        $this->handleUnsubscribe();
    }

    /**
     * A remote user subscribed.
     * @fixme move permission checks and event call into common code,
     *        currently we're doing the main logic in joingroup action
     *        and so have to repeat it here.
     */
    function handleSubscribe()
    {
        if ($this->oprofile->isGroup()) {
            // TRANS: Client error displayed when trying to subscribe a group to a list.
            $this->clientError(_m('Groups cannot subscribe to lists.'));
        }

        common_log(LOG_INFO, sprintf('Remote profile %s subscribing to local peopletag %s', $this->oprofile->getUri(), $this->peopletag->getBestName()));
        if ($this->peopletag->hasSubscriber($this->actor)) {
            // Already a member; we'll take it silently to aid in resolving
            // inconsistencies on the other side.
            return true;
        }

        // should we block those whom the tagger has blocked from listening to
        // his own updates?

        try {
            Profile_tag_subscription::add($this->peopletag, $this->actor);
        } catch (Exception $e) {
            // TRANS: Server error displayed when subscribing a remote user to a list fails.
            // TRANS: %1$s is a profile URI, %2$s is a list name.
            $this->serverError(sprintf(_m('Could not subscribe remote user %1$s to list %2$s.'),
                                       $this->oprofile->getUri(), $this->peopletag->getBestName()));
        }
    }

    /**
     * A remote user unsubscribed from our list.
     *
     * @return void
     * @throws Exception through clientError and serverError
     */
    function handleUnsubscribe()
    {
        if ($this->oprofile->isGroup()) {
            // TRANS: Client error displayed when trying to unsubscribe a group from a list.
            $this->clientError(_m('Groups cannot subscribe to lists.'));
        }

        common_log(LOG_INFO, sprintf('Remote profile %s unsubscribing from local peopletag %s', $this->oprofile->getUri(), $this->peopletag->getBestName()));
        try {
            Profile_tag_subscription::remove($this->peopletag->tagger, $this->peopletag->tag, $this->actor->id);
        } catch (Exception $e) {
            // TRANS: Client error displayed when trying to unsubscribe a remote user from a list fails.
            // TRANS: %1$s is a profile URL, %2$s is a list name.
            $this->serverError(sprintf(_m('Could not unsubscribe remote user %1$s from list %2$s.'),
                                       $this->oprofile->getUri(), $this->peopletag->getBestName()));
        }
    }
}

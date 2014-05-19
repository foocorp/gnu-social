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

if (!defined('STATUSNET')) {
    exit(1);
}

class PeopletagsalmonAction extends SalmonAction
{
    var $peopletag = null;

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            // TRANS: Client error displayed trying to perform an action without providing an ID.
            $this->clientError(_m('No ID.'));
        }

        $this->peopletag = Profile_list::getKV('id', $id);

        if (empty($this->peopletag)) {
            // TRANS: Client error displayed when referring to a non-existing list.
            $this->clientError(_m('No such list.'));
        }

        $oprofile = Ostatus_profile::getKV('peopletag_id', $id);

        if (!empty($oprofile)) {
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
     *
     * @throws NoProfileException from localProfile if missing locally stored Profile object
     */
    function handleSubscribe()
    {
        $oprofile = $this->ensureProfile();
        if (!$oprofile) {
            // TRANS: Client error displayed when referring to a non-existing remote list.
            $this->clientError(_m('Cannot read profile to set up list subscription.'));
        }
        if ($oprofile->isGroup()) {
            // TRANS: Client error displayed when trying to subscribe a group to a list.
            $this->clientError(_m('Groups cannot subscribe to lists.'));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} subscribing to local peopletag ".$this->peopletag->getBestName());
        $profile = $oprofile->localProfile();
        if ($this->peopletag->hasSubscriber($profile)) {
            // Already a member; we'll take it silently to aid in resolving
            // inconsistencies on the other side.
            return true;
        }

        // should we block those whom the tagger has blocked from listening to
        // his own updates?

        try {
            Profile_tag_subscription::add($this->peopletag, $profile);
        } catch (Exception $e) {
            // TRANS: Server error displayed when subscribing a remote user to a list fails.
            // TRANS: %1$s is a profile URI, %2$s is a list name.
            $this->serverError(sprintf(_m('Could not subscribe remote user %1$s to list %2$s.'),
                                       $oprofile->uri, $this->peopletag->getBestName()));
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
        $oprofile = $this->ensureProfile();
        if (!$oprofile instanceof Ostatus_profile) {
            // TRANS: Client error displayed when trying to unsubscribe from non-existing list.
            $this->clientError(_m('Cannot read profile to cancel list subscription.'));
        }
        if ($oprofile->isGroup()) {
            // TRANS: Client error displayed when trying to unsubscribe a group from a list.
            $this->clientError(_m('Groups cannot subscribe to lists.'));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} unsubscribing from local peopletag ".$this->peopletag->getBestName());
        try {
            $profile = $oprofile->localProfile();
            Profile_tag_subscription::remove($this->peopletag->tagger, $this->peopletag->tag, $profile->id);
        } catch (Exception $e) {
            // TRANS: Client error displayed when trying to unsubscribe a remote user from a list fails.
            // TRANS: %1$s is a profile URL, %2$s is a list name.
            $this->serverError(sprintf(_m('Could not unsubscribe remote user %1$s from list %2$s.'),
                                       $oprofile->getUri(), $this->peopletag->getBestName()));
        }
    }
}

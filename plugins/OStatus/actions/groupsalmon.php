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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */
class GroupsalmonAction extends SalmonAction
{
    var $group = null;

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            // TRANS: Client error.
            $this->clientError(_m('No ID.'));
        }

        $this->group = User_group::getKV('id', $id);

        if (empty($this->group)) {
            // TRANS: Client error.
            $this->clientError(_m('No such group.'));
        }


        $this->target = $this->group;

        $oprofile = Ostatus_profile::getKV('group_id', $id);
        if ($oprofile) {
            // TRANS: Client error.
            $this->clientError(_m('Cannot accept remote posts for a remote group.'));
        }

        return true;
    }

    /**
     * We've gotten a post event on the Salmon backchannel, probably a reply.
     */
    function handlePost()
    {
        // @fixme process all objects?
        switch ($this->activity->objects[0]->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            // TRANS: Client exception.
            throw new ClientException('Cannot handle that kind of post.');
        }

        // Notice must be to the attention of this group
        $context = $this->activity->context;

        if (empty($context->attention)) {
            // TRANS: Client exception.
            throw new ClientException("Not to the attention of anyone.");
        } else {
            $uri = common_local_url('groupbyid', array('id' => $this->group->id));
            if (!in_array($uri, $context->attention)) {
                // TRANS: Client exception.
                throw new ClientException("Not to the attention of this group.");
            }
        }

        $profile = $this->ensureProfile();
        $this->saveNotice();
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
        $this->handleJoin();
    }

    /**
     * Postel's law: consider an "unfollow" notification as a "leave".
     */
    function handleUnfollow()
    {
        $this->handleLeave();
    }

    /**
     * A remote user joined our group.
     * @fixme move permission checks and event call into common code,
     *        currently we're doing the main logic in joingroup action
     *        and so have to repeat it here.
     */
    function handleJoin()
    {
        $oprofile = $this->ensureProfile();
        if (!$oprofile) {
            // TRANS: Client error.
            $this->clientError(_m('Cannot read profile to set up group membership.'));
        }
        if ($oprofile->isGroup()) {
            // TRANS: Client error.
            $this->clientError(_m('Groups cannot join groups.'));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} joining local group {$this->group->nickname}");
        $profile = $oprofile->localProfile();

        if ($profile->isMember($this->group)) {
            // Already a member; we'll take it silently to aid in resolving
            // inconsistencies on the other side.
            return true;
        }

        if (Group_block::isBlocked($this->group, $profile)) {
            // TRANS: Client error displayed when trying to join a group the user is blocked from by a group admin.
            $this->clientError(_m('You have been blocked from that group by the admin.'), 403);
            return false;
        }

        try {
            $profile->joinGroup($this->group);
        } catch (Exception $e) {
            // TRANS: Server error. %1$s is a profile URI, %2$s is a group nickname.
            $this->serverError(sprintf(_m('Could not join remote user %1$s to group %2$s.'),
                                       $oprofile->uri, $this->group->nickname));
        }
    }

    /**
     * A remote user left our group.
     */
    function handleLeave()
    {
        $oprofile = $this->ensureProfile();
        if (!$oprofile) {
            // TRANS: Client error displayed when group membership cannot be cancelled
            // TRANS: because the remote profile could not be read.
            $this->clientError(_m('Cannot read profile to cancel group membership.'));
        }
        if ($oprofile->isGroup()) {
            // TRANS: Client error displayed when trying to have a group join another group.
            $this->clientError(_m('Groups cannot join groups.'));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} leaving local group {$this->group->nickname}");
        $profile = $oprofile->localProfile();

        try {
            $profile->leaveGroup($this->group);
        } catch (Exception $e) {
            // TRANS: Server error. %1$s is a profile URI, %2$s is a group nickname.
            $this->serverError(sprintf(_m('Could not remove remote user %1$s from group %2$s.'),
                                       $oprofile->uri, $this->group->nickname));
            return;
        }
    }
}

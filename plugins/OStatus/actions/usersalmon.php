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
 * @author James Walker <james@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class UsersalmonAction extends SalmonAction
{
    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            $this->clientError(_('No ID.'));
        }

        $this->user = User::staticGet('id', $id);

        if (empty($this->user)) {
            $this->clientError(_('No such user.'));
        }

        return true;
    }

    /**
     * We've gotten a post event on the Salmon backchannel, probably a reply.
     *
     * @todo validate if we need to handle this post, then call into
     * ostatus_profile's general incoming-post handling.
     */
    function handlePost()
    {
        common_log(LOG_INFO, "Received post of '{$this->activity->objects[0]->id}' from '{$this->activity->actor->id}'");

        // @fixme: process all activity objects?
        switch ($this->activity->objects[0]->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            throw new ClientException("Can't handle that kind of post.");
        }

        // Notice must either be a) in reply to a notice by this user
        // or b) to the attention of this user

        $context = $this->activity->context;

        if (!empty($context->replyToID)) {
            $notice = Notice::staticGet('uri', $context->replyToID);
            if (empty($notice)) {
                throw new ClientException("In reply to unknown notice");
            }
            if ($notice->profile_id != $this->user->id) {
                throw new ClientException("In reply to a notice not by this user");
            }
        } else if (!empty($context->attention)) {
            if (!in_array($this->user->uri, $context->attention) &&
                !in_array(common_profile_url($this->user->nickname), $context->attention)) {
                common_log(LOG_ERR, "{$this->user->uri} not in attention list (".implode(',', $context->attention).")");
                throw new ClientException("To the attention of user(s) not including this one!");
            }
        } else {
            throw new ClientException("Not to anyone in reply to anything!");
        }

        $existing = Notice::staticGet('uri', $this->activity->objects[0]->id);

        if (!empty($existing)) {
            common_log(LOG_ERR, "Not saving notice '{$existing->uri}'; already exists.");
            return;
        }

        $this->saveNotice();
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */

    function handleFollow()
    {
        $oprofile = $this->ensureProfile();
        if ($oprofile) {
            common_log(LOG_INFO, "Setting up subscription from remote {$oprofile->uri} to local {$this->user->nickname}");
            Subscription::start($oprofile->localProfile(),
                                $this->user->getProfile());
        } else {
            common_log(LOG_INFO, "Can't set up subscription from remote; missing profile.");
        }
    }

    /**
     * We've gotten an unfollow/unsubscribe notification from a remote user.
     * Check if we have a subscription relationship for them and kill it.
     *
     * @fixme probably catch exceptions on fail?
     */
    function handleUnfollow()
    {
        $oprofile = $this->ensureProfile();
        if ($oprofile) {
            common_log(LOG_INFO, "Canceling subscription from remote {$oprofile->uri} to local {$this->user->nickname}");
            Subscription::cancel($oprofile->localProfile(), $this->user->getProfile());
        } else {
            common_log(LOG_ERR, "Can't cancel subscription from remote, didn't find the profile");
        }
    }

    /**
     * Remote user likes one of our posts.
     * Confirm the post is ours, and save a local favorite event.
     */

    function handleFavorite()
    {
        $notice = $this->getNotice($this->activity->objects[0]);
        $profile = $this->ensureProfile()->localProfile();

        $old = Fave::pkeyGet(array('user_id' => $profile->id,
                                   'notice_id' => $notice->id));

        if (!empty($old)) {
            throw new ClientException("We already know that's a fave!");
        }

        if (!Fave::addNew($profile, $notice)) {
            throw new ClientException("Could not save new favorite.");
        }
    }

    /**
     * Remote user doesn't like one of our posts after all!
     * Confirm the post is ours, and save a local favorite event.
     */
    function handleUnfavorite()
    {
        $notice = $this->getNotice($this->activity->objects[0]);
        $profile = $this->ensureProfile()->localProfile();

        $fave = Fave::pkeyGet(array('user_id' => $profile->id,
                                   'notice_id' => $notice->id));
        if (empty($fave)) {
            throw new ClientException("Notice wasn't favorited!");
        }

        $fave->delete();
    }

    /**
     * @param ActivityObject $object
     * @return Notice
     * @throws ClientException on invalid input
     */
    function getNotice($object)
    {
        if (!$object) {
            throw new ClientException("Can't favorite/unfavorite without an object.");
        }

        switch ($object->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            throw new ClientException("Can't handle that kind of object for liking/faving.");
        }

        $notice = Notice::staticGet('uri', $object->id);

        if (empty($notice)) {
            throw new ClientException("Notice with ID $object->id unknown.");
        }

        if ($notice->profile_id != $this->user->id) {
            throw new ClientException("Notice with ID $object->id not posted by $this->user->id.");
        }

        return $notice;
    }

}

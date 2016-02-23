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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */
class UsersalmonAction extends SalmonAction
{
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->user = User::getByID($this->trimmed('id'));

        $this->target = $this->user->getProfile();

        // Notice must either be a) in reply to a notice by this user
        // or b) in reply to a notice to the attention of this user
        // or c) to the attention of this user
        // or d) reference the user as an activity:object

        $notice = null;

        if (!empty($this->activity->context->replyToID)) {
            try {
                $notice = Notice::getKV('uri', $this->activity->context->replyToID);
            } catch (NoResultException $e) {
                $notice = false;
            }
        }

        if ($notice instanceof Notice &&
                ($this->target->sameAs($notice->getProfile())
                    || array_key_exists($this->target->getID(), $notice->getAttentionProfileIDs())
                )) {
            // In reply to a notice either from or mentioning this user.
            common_debug('User is the owner or was in the attention list of thr:in-reply-to activity.');
        } elseif (!empty($this->activity->context->attention) &&
                   array_key_exists($this->target->getUri(), $this->activity->context->attention)) {
            // To the attention of this user.
            common_debug('User was in attention list of salmon slap.');
        } elseif (!empty($this->activity->objects) && $this->activity->objects[0]->id === $this->target->getUri()) {
            // The user is the object of this slap (unfollow for example)
            common_debug('User URI was the id of the salmon slap object.');
        } else {
            common_debug('User was NOT found in salmon slap context.');
            // TRANS: Client exception.
            throw new ClientException(_m('The owner of this salmon endpoint was not in the context of the carried slap.'));
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
            // TRANS: Client exception thrown when an undefied activity is performed.
            throw new ClientException(_m('Cannot handle that kind of post.'));
        }

        try {
            $this->saveNotice();
        } catch (AlreadyFulfilledException $e) {
            return;
        }
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */
    function handleFollow()
    {
        common_log(LOG_INFO, sprintf('Setting up subscription from remote %s to local %s', $this->oprofile->getUri(), $this->target->getNickname()));
        Subscription::start($this->actor, $this->target);
    }

    /**
     * We've gotten an unfollow/unsubscribe notification from a remote user.
     * Check if we have a subscription relationship for them and kill it.
     *
     * @fixme probably catch exceptions on fail?
     */
    function handleUnfollow()
    {
        common_log(LOG_INFO, sprintf('Canceling subscription from remote %s to local %s', $this->oprofile->getUri(), $this->target->getNickname()));
        try {
            Subscription::cancel($this->actor, $this->target);
        } catch (NoProfileException $e) {
            common_debug('Could not find profile for Subscription: '.$e->getMessage());
        }
    }

    function handleTag()
    {
        if ($this->activity->target->type == ActivityObject::_LIST) {
            if ($this->activity->objects[0]->type != ActivityObject::PERSON) {
                // TRANS: Client exception.
                throw new ClientException(_m('Not a person object.'));
            }
            // this is a peopletag
            $tagged = User::getKV('uri', $this->activity->objects[0]->id);

            if (!$tagged instanceof User) {
                // TRANS: Client exception.
                throw new ClientException(_m('Unidentified profile being listed.'));
            }

            if ($tagged->id !== $this->target->id) {
                // TRANS: Client exception.
                throw new ClientException(_m('This user is not the one being listed.'));
            }

            // save the list
            $list   = Ostatus_profile::ensureActivityObjectProfile($this->activity->target);

            $ptag = $list->localPeopletag();
            $result = Profile_tag::setTag($ptag->tagger, $tagged->id, $ptag->tag);
            if (!$result) {
                // TRANS: Client exception.
                throw new ClientException(_m('The listing could not be saved.'));
            }
        }
    }

    function handleUntag()
    {
        if ($this->activity->target->type == ActivityObject::_LIST) {
            if ($this->activity->objects[0]->type != ActivityObject::PERSON) {
                // TRANS: Client exception.
                throw new ClientException(_m('Not a person object.'));
            }
            // this is a peopletag
            $tagged = User::getKV('uri', $this->activity->objects[0]->id);

            if (!$tagged instanceof User) {
                // TRANS: Client exception.
                throw new ClientException(_m('Unidentified profile being unlisted.'));
            }

            if ($tagged->id !== $this->target->id) {
                // TRANS: Client exception.
                throw new ClientException(_m('This user is not the one being unlisted.'));
            }

            // save the list
            $list   = Ostatus_profile::ensureActivityObjectProfile($this->activity->target);

            $ptag = $list->localPeopletag();
            $result = Profile_tag::unTag($ptag->tagger, $tagged->id, $ptag->tag);

            if (!$result) {
                // TRANS: Client exception.
                throw new ClientException(_m('The listing could not be deleted.'));
            }
        }
    }

    /**
     * @param ActivityObject $object
     * @return Notice
     * @throws ClientException on invalid input
     */
    function getNotice(ActivityObject $object)
    {
        switch ($object->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            // TRANS: Client exception.
            throw new ClientException(_m('Cannot handle that kind of object for liking/faving.'));
        }

        $notice = Notice::getKV('uri', $object->id);

        if (!$notice instanceof Notice) {
            // TRANS: Client exception. %s is an object ID.
            throw new ClientException(sprintf(_m('Notice with ID %s unknown.'),$object->id));
        }

        if ($notice->profile_id != $this->target->id) {
            // TRANS: Client exception. %1$s is a notice ID, %2$s is a user ID.
            throw new ClientException(sprintf(_m('Notice with ID %1$s not posted by %2$s.'), $object->id, $this->target->id));
        }

        return $notice;
    }
}

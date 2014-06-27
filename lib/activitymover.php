<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Title of module
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
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Class comment
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ActivityMover extends QueueHandler
{
    function transport()
    {
        return 'actmove';
    }

    function handle($data)
    {
        list ($act, $sink, $userURI, $remoteURI) = $data;

        $user   = User::getKV('uri', $userURI);
        try {
            $remote = Profile::fromUri($remoteURI);
        } catch (UnknownUriException $e) {
            // Don't retry. It's hard to tell whether it's because of
            // lookup failures or because the URI is permanently gone.
            // If we knew it was temporary, we'd return false here.
            return true;
        }

        try {
            $this->moveActivity($act, $sink, $user, $remote);
        } catch (ClientException $cex) {
            $this->log(LOG_WARNING,
                       $cex->getMessage());
            // "don't retry me"
            return true;
        } catch (ServerException $sex) {
            $this->log(LOG_WARNING,
                       $sex->getMessage());
            // "retry me" (because we think the server might handle it next time)
            return false;
        } catch (Exception $ex) {
            $this->log(LOG_WARNING,
                       $ex->getMessage());
            // "don't retry me"
            return true;
        }
    }

    function moveActivity($act, $sink, $user, $remote)
    {
        if (empty($user)) {
            // TRANS: Exception thrown if a non-existing user is provided. %s is a user ID.
            throw new Exception(sprintf(_('No such user "%s".'),$act->actor->id));
        }

        switch ($act->verb) {
/*        case ActivityVerb::FAVORITE:
            $this->log(LOG_INFO,
                       "Moving favorite of {$act->objects[0]->id} by ".
                       "{$act->actor->id} to {$remote->nickname}.");
            // push it, then delete local
            $sink->postActivity($act);
            $notice = Notice::getKV('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $fave = Fave::pkeyGet(array('user_id' => $user->id,
                                            'notice_id' => $notice->id));
                $fave->delete();
            }
            break;*/
        case ActivityVerb::POST:
            $this->log(LOG_INFO,
                       "Moving notice {$act->objects[0]->id} by ".
                       "{$act->actor->id} to {$remote->nickname}.");
            // XXX: send a reshare, not a post
            $sink->postActivity($act);
            $notice = Notice::getKV('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $notice->delete();
            }
            break;
        case ActivityVerb::JOIN:
            $this->log(LOG_INFO,
                       "Moving group join of {$act->objects[0]->id} by ".
                       "{$act->actor->id} to {$remote->nickname}.");
            $sink->postActivity($act);
            $group = User_group::getKV('uri', $act->objects[0]->id);
            if (!empty($group)) {
                $user->leaveGroup($group);
            }
            break;
        case ActivityVerb::FOLLOW:
            if ($act->actor->id === $user->getUri()) {
                $this->log(LOG_INFO,
                           "Moving subscription to {$act->objects[0]->id} by ".
                           "{$act->actor->id} to {$remote->nickname}.");
                $sink->postActivity($act);
                try {
                    $other = Profile::fromUri($act->objects[0]->id);
                    Subscription::cancel($user->getProfile(), $other);
                } catch (UnknownUriException $e) {
                    // Can't cancel subscription if we don't know who to alert
                }
            } else {
                $otherUser = User::getKV('uri', $act->actor->id);
                if (!empty($otherUser)) {
                    $this->log(LOG_INFO,
                               "Changing sub to {$act->objects[0]->id}".
                               "by {$act->actor->id} to {$remote->nickname}.");
                    $otherProfile = $otherUser->getProfile();
                    Subscription::start($otherProfile, $remote);
                    Subscription::cancel($otherProfile, $user->getProfile());
                } else {
                    $this->log(LOG_NOTICE,
                               "Not changing sub to {$act->objects[0]->id}".
                               "by remote {$act->actor->id} ".
                               "to {$remote->nickname}.");
                }
            }
            break;
        }
    }

    /**
     * Log some data
     *
     * Add a header for our class so we know who did it.
     *
     * @param int    $level   Log level, like LOG_ERR or LOG_INFO
     * @param string $message Message to log
     *
     * @return void
     */
    protected function log($level, $message)
    {
        common_log($level, "ActivityMover: " . $message);
    }
}

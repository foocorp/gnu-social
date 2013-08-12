<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class UserrssAction extends Rss10Action
{
    var $tag  = null;

    function prepare($args)
    {
        parent::prepare($args);
        $nickname   = $this->trimmed('nickname');
        $this->user = User::staticGet('nickname', $nickname);
        $this->tag  = $this->trimmed('tag');

        if (!$this->user) {
            // TRANS: Client error displayed when user not found for an action.
            $this->clientError(_('No such user.'));
            return false;
        } else {
            if (!empty($this->tag)) {
                $this->notices = $this->getTaggedNotices();
            } else {
                $this->notices = $this->getNotices();
            }
            return true;
        }
    }

    function getTaggedNotices()
    {
        $notice = $this->user->getTaggedNotices(
            $this->tag,
            0,
            ($this->limit == 0) ? NOTICES_PER_PAGE : $this->limit,
            0,
            0
        );

        $notices = array();
        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }


    function getNotices()
    {
        $notice = $this->user->getNotices(
            0,
            ($this->limit == 0) ? NOTICES_PER_PAGE : $this->limit
        );

        $notices = array();
        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    function getChannel()
    {
        $user = $this->user;
        $profile = $user->getProfile();
        $c = array('url' => common_local_url('userrss',
                                             array('nickname' =>
                                                   $user->nickname)),
                   // TRANS: Message is used as link title. %s is a user nickname.
                   'title' => sprintf(_('%s timeline'), $user->nickname),
                   'link' => $profile->profileurl,
                   // TRANS: Message is used as link description. %1$s is a username, %2$s is a site name.
                   'description' => sprintf(_('Updates from %1$s on %2$s!'),
                                            $user->nickname, common_config('site', 'name')));
        return $c;
    }

    function getImage()
    {
        $user = $this->user;
        $profile = $user->getProfile();
        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return null;
        }
        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        return ($avatar) ? $avatar->url : null;
    }

    // override parent to add X-SUP-ID URL

    function initRss($limit=0)
    {
        $url = common_local_url('sup', null, null, $this->user->id);
        header('X-SUP-ID: '.$url);
        parent::initRss($limit);
    }

    function isReadOnly($args)
    {
        return true;
    }
}

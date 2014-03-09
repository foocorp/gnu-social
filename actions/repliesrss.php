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

class RepliesrssAction extends Rss10Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);
        $nickname = $this->trimmed('nickname');
        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when providing a non-existing nickname in a RSS 1.0 action.
            $this->clientError(_('No such user.'));
        } else {
            $this->notices = $this->getNotices($this->limit);
            return true;
        }
    }

    function getNotices($limit=0)
    {
        $user = $this->user;

        $notice = $user->getReplies(0, ($limit == 0) ? 48 : $limit);

        $notices = array();

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    function getChannel()
    {
        $user = $this->user;
        $c = array('url' => common_local_url('repliesrss',
                                             array('nickname' =>
                                                   $user->nickname)),
                   // TRANS: RSS reply feed title. %s is a user nickname.
                   'title' => sprintf(_("Replies to %s"), $user->nickname),
                   'link' => common_local_url('replies',
                                              array('nickname' =>
                                                    $user->nickname)),
                   // TRANS: RSS reply feed description.
                   // TRANS: %1$s is a user nickname, %2$s is the StatusNet site name.
                   'description' => sprintf(_('Replies to %1$s on %2$s.'),
                                              $user->nickname, common_config('site', 'name')));
        return $c;
    }

    function getImage()
    {
        $profile = $this->user->getProfile();
        return $profile->avatarUrl(AVATAR_PROFILE_SIZE);
    }

    function isReadOnly($args)
    {
        return true;
    }
}

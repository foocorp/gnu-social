<?php
/**
 * RSS feed for user and friends timeline action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/rssaction.php';

/**
 * RSS feed for user and friends timeline.
 *
 * Formatting of RSS handled by Rss10Action
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class AllrssAction extends Rss10Action
{
    var $user = null;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     *
     */
    function prepare($args)
    {
        parent::prepare($args);
        $nickname   = $this->trimmed('nickname');
        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error when user not found for an rss related action.
            $this->clientError(_('No such user.'));
        } else {
            $this->notices = $this->getNotices($this->limit);
            return true;
        }
    }

    /**
     * Get notices
     *
     * @param integer $limit max number of notices to return
     *
     * @return array notices
     */
    function getNotices($limit=0)
    {
        $stream = new InboxNoticeStream($this->user->getProfile());
        $notice = $stream->getNotices(0, $limit, null, null);

        $notices = array();

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

     /**
     * Get channel.
     *
     * @return array associative array on channel information
     */
    function getChannel()
    {
        $user = $this->user;
        $c    = array('url' => common_local_url('allrss',
                                             array('nickname' =>
                                                   $user->nickname)),
                   // TRANS: Message is used as link title. %s is a user nickname.
                   'title' => sprintf(_('%s and friends'), $user->nickname),
                   'link' => common_local_url('all',
                                             array('nickname' =>
                                                   $user->nickname)),
                   // TRANS: Message is used as link description. %1$s is a username, %2$s is a site name.
                   'description' => sprintf(_('Updates from %1$s and friends on %2$s!'),
                                            $user->nickname, common_config('site', 'name')));
        return $c;
    }

    /**
     * Get image.
     *
     * @return string user avatar URL or null
     */
    function getImage()
    {
        $user    = $this->user;
        $profile = $user->getProfile();
        if (!$profile) {
            return null;
        }
        return $profile->avatarUrl(AVATAR_PROFILE_SIZE);
    }
}

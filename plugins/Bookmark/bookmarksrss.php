<?php
/**
 * RSS feed for user bookmarks action class.
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
require_once 'bookmarksnoticestream.php';

/**
 * RSS feed for user bookmarks action class.
 *
 * Formatting of RSS handled by Rss10Action
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Stephane Berube <chimo@chromic.org> (modified 'favoritesrss.php' to show bookmarks instead)
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class BookmarksrssAction extends Rss10Action
{
    /** The user whose bookmarks to display */

    var $user = null;

    /**
     * Find the user to display by supplied nickname
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return boolean success
     */
    function prepare($args)
    {        
        parent::prepare($args);

        $nickname   = $this->trimmed('nickname');
        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when trying to get the RSS feed with bookmarks of a user that does not exist.
            $this->clientError(_('No such user.'));
            return false;
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
        $user    = $this->user;

        $notice = new BookmarksNoticeStream($this->user->id, true);
        $notice = $notice->getNotices(0, NOTICES_PER_PAGE);

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
        $c    = array('url' => common_local_url('bookmarksrss',
                                        array('nickname' =>
                                        $user->nickname)),
                   // TRANS: Title of RSS feed with bookmarks of a user.
                   // TRANS: %s is a user's nickname.
                   'title' => sprintf(_("%s's bookmarks"), $user->nickname),
                   'link' => common_local_url('bookmarks',
                                        array('nickname' =>
                                        $user->nickname)),
                   // TRANS: Desciption of RSS feed with bookmarks of a user.
                   // TRANS: %1$s is a user's nickname, %2$s is the name of the StatusNet site.
                   'description' => sprintf(_('Bookmarks posted by %1$s on %2$s!'),
                                        $user->nickname, common_config('site', 'name')));
        return $c;
    }

    /**
     * Get image.
     *
     * @return void
    */
    function getImage()
    {
        return null;
    }

}

<?php
/**
 * RSS feed for user favorites action class.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * RSS feed for user favorites action class.
 *
 * Formatting of RSS handled by Rss10Action
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class FavoritesrssAction extends TargetedRss10Action
{
    protected function getNotices()
    {
        // is this our own stream?
        $own = $this->scoped instanceof Profile ? $this->target->getID() === $this->scoped->getID() : false;

        $stream = Fave::stream($this->target->getID(), 0, $this->limit, $own);
        return $stream->fetchAll();
    }

     /**
     * Get channel.
     *
     * @return array associative array on channel information
     */
    function getChannel()
    {
        $user = $this->user;
        $c    = array('url' => common_local_url('favoritesrss',
                                        array('nickname' =>
                                        $user->nickname)),
                   // TRANS: Title of RSS feed with favourite notices of a user.
                   // TRANS: %s is a user's nickname.
                   'title' => sprintf(_("%s's favorite notices"), $user->nickname),
                   'link' => common_local_url('showfavorites',
                                        array('nickname' =>
                                        $user->nickname)),
                   // TRANS: Desciption of RSS feed with favourite notices of a user.
                   // TRANS: %1$s is a user's nickname, %2$s is the name of the StatusNet site.
                   'description' => sprintf(_('Updates favored by %1$s on %2$s!'),
                                        $user->nickname, common_config('site', 'name')));
        return $c;
    }
}

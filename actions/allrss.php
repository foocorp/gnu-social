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

if (!defined('GNUSOCIAL')) { exit(1); }

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
class AllrssAction extends TargetedRss10Action
{
    protected function getNotices()
    {
        $stream = new InboxNoticeStream($this->target, $this->scoped);
        return $stream->getNotices(0, $this->limit)->fetchAll();
    }

     /**
     * Get channel.
     *
     * @return array associative array on channel information
     */
    function getChannel()
    {
        $c    = array('url' => common_local_url('allrss',
                                             array('nickname' =>
                                                   $this->target->getNickname())),
                   // TRANS: Message is used as link title. %s is a user nickname.
                   'title' => sprintf(_('%s and friends'), $this->target->getNickname()),
                   'link' => common_local_url('all',
                                             array('nickname' =>
                                                   $this->target->getNickname())),
                   // TRANS: Message is used as link description. %1$s is a username, %2$s is a site name.
                   'description' => sprintf(_('Updates from %1$s and friends on %2$s!'),
                                            $this->target->getNickname(), common_config('site', 'name')));
        return $c;
    }
}

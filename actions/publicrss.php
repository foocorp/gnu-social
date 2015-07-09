<?php
/**
 * Public RSS action class.
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
 * RSS feed for public timeline.
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
class PublicrssAction extends Rss10Action
{
    /**
     * Read arguments and initialize members
     *
     * @param array $args Arguments from $_REQUEST
     * @return boolean success
     */
    function prepare($args)
    {
        parent::prepare($args);
        $this->notices = $this->getNotices($this->limit);
        return true;
    }

    /**
     * Initialization.
     *
     * @return boolean true
     */
    function init()
    {
        return true;
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
        $notices = array();
        $notice  = Notice::publicStream(0, ($limit == 0) ? 48 : $limit);
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
        $sitename = common_config('site', 'name');
        $c = array(
              'url' => common_local_url('publicrss'),
            // TRANS: Public RSS feed title. %s is the StatusNet site name.
              'title' => sprintf(_('%s public timeline'), $sitename),
              'link' => common_local_url('public'),
            // TRANS: Public RSS feed description. %s is the StatusNet site name.
              'description' => sprintf(_('%s updates from everyone.'), $sitename));
        return $c;
    }

    /**
     * Get image.
     *
     * @return nothing
     */
    function getImage()
    {
        // nop
    }

    function isReadOnly($args)
    {
        return true;
    }
}

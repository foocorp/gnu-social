<?php
/**
 * RSS feed for notice search action class.
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
 * RSS feed for notice search action class.
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
class NoticesearchrssAction extends Rss10Action
{
    function init()
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->notices = $this->getNotices();
        return true;
    }

    function getNotices($limit=0)
    {
        $q = $this->trimmed('q');
        $notices = array();

        $notice = new Notice();

        $search_engine = $notice->getSearchEngine('notice');
        $search_engine->set_sort_mode('chron');

        if (!$limit) $limit = 20;
        $search_engine->limit(0, $limit, true);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        } else {
            $cnt = $notice->find();
        }

        if ($cnt > 0) {
            while ($notice->fetch()) {
                $notices[] = clone($notice);
            }
        }

        return $notices;
    }

    function getChannel()
    {
        $q = $this->trimmed('q');
        $c = array('url' => common_local_url('noticesearchrss', array('q' => $q)),
                   // TRANS: RSS notice search feed title. %s is the query.
                   'title' => sprintf(_('Updates with "%s"'), $q),
                   'link' => common_local_url('noticesearch', array('q' => $q)),
                   // TRANS: RSS notice search feed description.
                   // TRANS: %1$s is the query, %2$s is the StatusNet site name.
                   'description' => sprintf(_('Updates matching search term "%1$s" on %2$s.'),
                                            $q, common_config('site', 'name')));
        return $c;
    }

    function getImage()
    {
        return null;
    }

    function isReadOnly($args)
    {
        return true;
    }
}

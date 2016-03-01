<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notice results
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
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Stream of notice search results
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class SearchNoticeStream extends ScopingNoticeStream
{
    function __construct($q, Profile $scoped=null)
    {
        parent::__construct(new RawSearchNoticeStream($q), $scoped);
    }
}

class RawSearchNoticeStream extends NoticeStream
{
    protected $q;

    function __construct($q)
    {
        $this->q = $q;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $search_engine = $notice->getSearchEngine('notice');
        $search_engine->set_sort_mode('chron');

        $search_engine->limit($offset, $limit);

        $ids = array();

        // wtf?

        $search_engine->query($this->q);

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        return $ids;
    }
}

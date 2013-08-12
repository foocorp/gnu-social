<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A notice stream that filters its upstream content
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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A class for presenting a filtered notice stream based on an upstream stream
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

abstract class FilteringNoticeStream extends NoticeStream
{
    protected $upstream;

    function __construct($upstream)
    {
        $this->upstream = $upstream;
    }

    abstract function filter($notice);

    function getNotices($offset, $limit, $sinceId=null, $maxId=null)
    {
        // "offset" is virtual; we have to get a lot
        $total = $offset + $limit;

        $filtered = array();

        $startAt = 0;
        $askFor  = $total;

        // Keep going till we have $total notices in $notices array,
        // or we get nothing from upstream.

        $results = null;
        $round = 0;

        do {

            $raw = $this->upstream->getNotices($startAt, $askFor, $sinceId, $maxId);

            $results = $raw->N;

            if ($results == 0) {
                break;
            }

            $notices = $raw->fetchAll();
            
            $this->prefill($notices);

            foreach ($notices as $notice) {
                if ($this->filter($notice)) {
                    $filtered[] = $notice;
                    if (count($filtered) >= $total) {
                        break;
                    }
                }
            }

            // XXX: make these smarter; factor hit rate into $askFor

            $startAt += $askFor;
            
            $hits = count($filtered);

            $lastAsk = $askFor;

            if ($hits === 0) {
                $askFor = max(min(2 * $askFor, NOTICES_PER_PAGE * 50), NOTICES_PER_PAGE);
            } else {
                $askFor = max(min(intval(ceil(($total - $hits)*$startAt/$hits)), NOTICES_PER_PAGE * 50), NOTICES_PER_PAGE);
            }

            $round++;

        } while (count($filtered) < $total && $results >= $lastAsk);

        return new ArrayWrapper(array_slice($filtered, $offset, $limit));
    }

    function getNoticeIds($offset, $limit, $sinceId, $maxId)
    {
        $notices = $this->getNotices($offset, $limit, $sinceId, $maxId);

        $ids = array();

        while ($notices->fetch()) {
            $ids[] = $notices->id;
        }

        return $ids;
    }

    function prefill($notices)
    {
        return;
    }
}

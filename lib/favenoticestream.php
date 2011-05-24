<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Notice stream for favorites
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
 * Notice stream for favorites
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class FaveNoticeStream extends ScopingNoticeStream
{
    function __construct($user_id, $own, $profile = -1)
    {
        $stream = new RawFaveNoticeStream($user_id, $own);
        if ($own) {
            $key = 'fave:ids_by_user_own:'.$user_id;
        } else {
            $key = 'fave:ids_by_user:'.$user_id;
        }
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        parent::__construct(new CachingNoticeStream($stream, $key),
                            $profile);
    }
}

/**
 * Raw notice stream for favorites
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawFaveNoticeStream extends NoticeStream
{
    protected $user_id;
    protected $own;

    function __construct($user_id, $own)
    {
        $this->user_id = $user_id;
        $this->own     = $own;
    }

    /**
     * Note that the sorting for this is by order of *fave* not order of *notice*.
     *
     * @fixme add since_id, max_id support?
     *
     * @param <type> $user_id
     * @param <type> $own
     * @param <type> $offset
     * @param <type> $limit
     * @param <type> $since_id
     * @param <type> $max_id
     * @return <type>
     */
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $fav = new Fave();
        $qry = null;

        if ($this->own) {
            $qry  = 'SELECT fave.* FROM fave ';
            $qry .= 'WHERE fave.user_id = ' . $this->user_id . ' ';
        } else {
             $qry =  'SELECT fave.* FROM fave ';
             $qry .= 'INNER JOIN notice ON fave.notice_id = notice.id ';
             $qry .= 'WHERE fave.user_id = ' . $this->user_id . ' ';
             $qry .= 'AND notice.is_local != ' . Notice::GATEWAY . ' ';
        }

        if ($since_id != 0) {
            $qry .= 'AND notice_id > ' . $since_id . ' ';
        }

        if ($max_id != 0) {
            $qry .= 'AND notice_id <= ' . $max_id . ' ';
        }

        // NOTE: we sort by fave time, not by notice time!

        $qry .= 'ORDER BY modified DESC ';

        if (!is_null($offset)) {
            $qry .= "LIMIT $limit OFFSET $offset";
        }

        $fav->query($qry);

        $ids = array();

        while ($fav->fetch()) {
            $ids[] = $fav->notice_id;
        }

        $fav->free();
        unset($fav);

        return $ids;
    }
}


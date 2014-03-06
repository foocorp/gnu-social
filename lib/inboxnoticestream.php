<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices for a profile's "all" feed
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
 * @category  NoticeStream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2011 StatusNet, Inc.
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

/**
 * Stream of notices for a profile's "all" feed
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2011 StatusNet, Inc.
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class InboxNoticeStream extends ScopingNoticeStream
{
    /**
     * Constructor
     *
     * @param Profile $target Profile to get a stream for
     * @param Profile $scoped Currently scoped profile (if null, it is fetched)
     */
    function __construct(Profile $target, Profile $scoped=null)
    {
        if ($scoped === null) {
            $scoped = Profile::current();
        }
        // Note: we don't use CachingNoticeStream since RawInboxNoticeStream
        // uses Inbox::getKV(), which is cached.
        parent::__construct(new RawInboxNoticeStream($target), $scoped);
    }
}

/**
 * Raw stream of notices for the target's inbox
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawInboxNoticeStream extends NoticeStream
{
    protected $target  = null;
    protected $inbox = null;

    /**
     * Constructor
     *
     * @param Profile $target Profile to get a stream for
     */
    function __construct(Profile $target)
    {
        $this->target  = $target;
    }

    /**
     * Get IDs in a range
     *
     * @param int $offset   Offset from start
     * @param int $limit    Limit of number to get
     * @param int $since_id Since this notice
     * @param int $max_id   Before this notice
     *
     * @return Array IDs found
     */
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();
        $notice->selectAdd();
        $notice->selectAdd('notice_id');
        // Reply is a class for mentions
        $notice->joinAdd(array('id', 'reply:notice_id'));

        $notice->profile_id = $this->target->id;
        $notice->limit($offset, $limit);
        $notice->orderBy('created DESC');

        if (!$notice->find()) {
            return array();
        }

        $ids = $notice->fetchAll('notice_id');

        return $ids;
    }

    function getNotices($offset, $limit, $sinceId, $maxId)
    {
        $all = array();

        do {

            $ids = $this->getNoticeIds($offset, $limit, $sinceId, $maxId);

            $notices = Notice::pivotGet('id', $ids);

            // By default, takes out false values

            $notices = array_filter($notices);

            $all = array_merge($all, $notices);

            if (count($notices < count($ids))) {
                $offset += $limit;
                $limit  -= count($notices);
            }

        } while (count($notices) < count($ids) && count($ids) > 0);

        return new ArrayWrapper($all);
    }
}

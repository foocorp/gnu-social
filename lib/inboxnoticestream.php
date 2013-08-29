<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices for the user's inbox
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
 * Stream of notices for the user's inbox
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class InboxNoticeStream extends ScopingNoticeStream
{
    /**
     * Constructor
     *
     * @param User $user User to get a stream for
     */
    function __construct($user, $profile = -1)
    {
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        // Note: we don't use CachingNoticeStream since RawInboxNoticeStream
        // uses Inbox::getKV(), which is cached.
        parent::__construct(new RawInboxNoticeStream($user), $profile);
    }
}

/**
 * Raw stream of notices for the user's inbox
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
    protected $user  = null;
    protected $inbox = null;

    /**
     * Constructor
     *
     * @param User $user User to get a stream for
     */
    function __construct($user)
    {
        $this->user  = $user;
        $this->inbox = Inbox::getKV('user_id', $user->id);
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
        if (empty($this->inbox)) {
            $this->inbox = Inbox::fromNoticeInbox($user_id);
            if (empty($this->inbox)) {
                return array();
            } else {
                $this->inbox->encache();
            }
        }

        $ids = $this->inbox->unpack();

        if (!empty($since_id)) {
            $newids = array();
            foreach ($ids as $id) {
                if ($id > $since_id) {
                    $newids[] = $id;
                }
            }
            $ids = $newids;
        }

        if (!empty($max_id)) {
            $newids = array();
            foreach ($ids as $id) {
                if ($id <= $max_id) {
                    $newids[] = $id;
                }
            }
            $ids = $newids;
        }

        $ids = array_slice($ids, $offset, $limit);

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

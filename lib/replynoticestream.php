<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of mentions of me
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
 * Stream of mentions of me
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ReplyNoticeStream extends ScopingNoticeStream
{
    function __construct($userId, $profile=-1)
    {
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        parent::__construct(new CachingNoticeStream(new RawReplyNoticeStream($userId),
                                                    'reply:stream:' . $userId),
                            $profile);
    }
}

/**
 * Raw stream of mentions of me
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RawReplyNoticeStream extends NoticeStream
{
    protected $userId;

    function __construct($userId)
    {
        parent::__construct();
        $this->userId = $userId;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $reply = new Reply();

        $reply->selectAdd();
        $reply->selectAdd('notice_id');

        $reply->whereAdd(sprintf('reply.profile_id = %u', $this->userId));

        Notice::addWhereSinceId($reply, $since_id, 'notice_id', 'reply.modified');
        Notice::addWhereMaxId($reply, $max_id, 'notice_id', 'reply.modified');

        if (!empty($this->selectVerbs)) {
            // this is a little special since we have to join in Notice
            $reply->joinAdd(array('notice_id', 'notice:id'));

            $filter = array_keys(array_filter($this->selectVerbs));
            if (!empty($filter)) {
                // include verbs in selectVerbs with values that equate to true
                $reply->whereAddIn('notice.verb', $filter, 'string');
            }

            $filter = array_keys(array_filter($this->selectVerbs, function ($v) { return !$v; }));
            if (!empty($filter)) {
                // exclude verbs in selectVerbs with values that equate to false
                $reply->whereAddIn('!notice.verb', $filter, 'string');
            }
        }

        $reply->orderBy('reply.modified DESC, reply.notice_id DESC');

        if (!is_null($offset)) {
            $reply->limit($offset, $limit);
        }

        $ids = array();

        if ($reply->find()) {
            while ($reply->fetch()) {
                $ids[] = $reply->notice_id;
            }
        }

        return $ids;
    }
}

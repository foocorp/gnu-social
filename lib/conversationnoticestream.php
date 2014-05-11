<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Notice stream for a conversation
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
 * Notice stream for a conversation
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ConversationNoticeStream extends ScopingNoticeStream
{
    function __construct($id, $profile = -1)
    {
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }

        parent::__construct(new RawConversationNoticeStream($id),
                            $profile);
    }
}

/**
 * Notice stream for a conversation
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawConversationNoticeStream extends NoticeStream
{
    protected $id;

    function __construct($id)
    {
        $this->id = $id;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $conv = Conversation::getKV('id', $this->id);
        if (!$conv instanceof Conversation) {
            throw new ServerException('Could not find conversation');
        }
        $notice = new Notice();
        // SELECT
        $notice->selectAdd();
        $notice->selectAdd('id');

        // WHERE
        $notice->conversation = $this->id;
        if (!empty($since_id)) {
            $notice->whereAdd(sprintf('notice.id > %d', $since_id));
        }
        if (!empty($max_id)) {
            $notice->whereAdd(sprintf('notice.id <= %d', $max_id));
        }
        $notice->limit($offset, $limit);

        // ORDER BY
        // currently imitates the previously used "_reverseChron" sorting
        $notice->orderBy('notice.created DESC');
        return $notice->fetchAll('id');
    }
}

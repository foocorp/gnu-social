<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices for a group
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
 * Stream of notices for a group
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupNoticeStream extends ScopingNoticeStream
{
    var $group;

    function __construct($group, Profile $scoped=null)
    {
        $this->group = $group;

        parent::__construct(new CachingNoticeStream(new RawGroupNoticeStream($group),
                                                    'user_group:notice_ids:' . $group->id),
                            $scoped);
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        if ($this->impossibleStream()) {
            return array();
        } else {
            return parent::getNoticeIds($offset, $limit, $since_id, $max_id);
        }
    }

    function getNotices($offset, $limit, $sinceId = null, $maxId = null)
    {
        if ($this->impossibleStream()) {
            return new ArrayWrapper(array());
        } else {
            return parent::getNotices($offset, $limit, $sinceId, $maxId);
        }
    }

    function impossibleStream() 
    {
        if ($this->group->force_scope &&
            (!$this->scoped instanceof Profile || $this->scoped->isMember($this->group))) {
            return true;
        }

        return false;
    }
}

/**
 * Stream of notices for a group
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawGroupNoticeStream extends NoticeStream
{
    protected $group;

    function __construct($group)
    {
        $this->group = $group;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $inbox = new Group_inbox();

        $inbox->group_id = $this->group->id;

        $inbox->selectAdd();
        $inbox->selectAdd('notice_id');

        Notice::addWhereSinceId($inbox, $since_id, 'notice_id');
        Notice::addWhereMaxId($inbox, $max_id, 'notice_id');

        $inbox->orderBy('created DESC, notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices by a profile
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
 * Stream of notices by a profile
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ProfileNoticeStream extends ScopingNoticeStream
{
    var $streamProfile;
    var $userProfile;

    function __construct($profile, $userProfile = -1)
    {
        if (is_int($userProfile) && $userProfile == -1) {
            $userProfile = Profile::current();
        }
        $this->streamProfile = $profile;
        $this->userProfile   = $userProfile;
        parent::__construct(new CachingNoticeStream(new RawProfileNoticeStream($profile),
                                                    'profile:notice_ids:' . $profile->id),
                            $userProfile);
    }

    function getNoticeIds($offset, $limit, $since_id=null, $max_id=null)
    {
        if ($this->impossibleStream()) {
            return array();
        } else {
            return parent::getNoticeIds($offset, $limit, $since_id, $max_id);
        }
    }

    function getNotices($offset, $limit, $since_id=null, $max_id=null)
    {
        if ($this->impossibleStream()) {
            throw new PrivateStreamException($this->streamProfile, $this->userProfile);
        } else {
            return parent::getNotices($offset, $limit, $since_id, $max_id);
        }
    }

    function impossibleStream() 
    {
        if (!$this->streamProfile->readableBy($this->userProfile)) {
            // cannot read because it's a private stream and either noone's logged in or they are not subscribers
            return true;
        }

        // If it's a spammy stream, and no user or not a moderator

        if (common_config('notice', 'hidespam')) {
            if ($this->streamProfile->hasRole(Profile_role::SILENCED) &&
                (empty($this->userProfile) || (($this->userProfile->id !== $this->streamProfile->id) && !$this->userProfile->hasRight(Right::REVIEWSPAM)))) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Raw stream of notices by a profile
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RawProfileNoticeStream extends NoticeStream
{
    protected $profile;
    protected $selectVerbs = array();   // select all verbs

    function __construct($profile)
    {
        parent::__construct();
        $this->profile = $profile;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->profile_id = $this->profile->id;

        $notice->selectAdd();
        $notice->selectAdd('id');

        Notice::addWhereSinceId($notice, $since_id);
        Notice::addWhereMaxId($notice, $max_id);

        if (!empty($this->selectVerbs)) {
            $notice->whereAddIn('verb', $this->selectVerbs, $notice->columnType('verb'));
        }
        if (!empty($this->unselectVerbs)) {
            $notice->whereAddIn('!verb', $this->unselectVerbs, $notice->columnType('verb'));
        }

        $notice->orderBy('created DESC, id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $notice->find();

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }
}

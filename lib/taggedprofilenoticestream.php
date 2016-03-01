<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices by a profile with a given tag
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
 * Stream of notices with a given profile and tag
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class TaggedProfileNoticeStream extends ScopingNoticeStream
{
    function __construct($profile, $tag, Profile $scoped=null)
    {
        parent::__construct(new CachingNoticeStream(new RawTaggedProfileNoticeStream($profile, $tag),
                                                    'profile:notice_ids_tagged:'.$profile->id.':'.Cache::keyize($tag)),
                            $scoped);
    }
}

/**
 * Raw stream of notices with a given profile and tag
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RawTaggedProfileNoticeStream extends NoticeStream
{
    protected $profile;
    protected $tag;

    function __construct($profile, $tag)
    {
        $this->profile = $profile;
        $this->tag     = $tag;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        // XXX It would be nice to do this without a join
        // (necessary to do it efficiently on accounts with long history)

        $notice = new Notice();

        $query =
          "select id from notice join notice_tag on id=notice_id where tag='".
          $notice->escape($this->tag) .
          "' and profile_id=" . intval($this->profile->id);

        $since = Notice::whereSinceId($since_id, 'id', 'notice.created');
        if ($since) {
            $query .= " and ($since)";
        }

        $max = Notice::whereMaxId($max_id, 'id', 'notice.created');
        if ($max) {
            $query .= " and ($max)";
        }

        $query .= ' order by notice.created DESC, id DESC';

        if (!is_null($offset)) {
            $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }

        $notice->query($query);

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }
}

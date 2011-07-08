<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices for a list
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
 * Stream of notices for a list
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class PeopletagNoticeStream extends ScopingNoticeStream
{
    function __construct($plist, $profile = -1)
    {
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        parent::__construct(new CachingNoticeStream(new RawPeopletagNoticeStream($plist),
                                                    'profile_list:notice_ids:' . $plist->id),
                            $profile);
    }
}

/**
 * Stream of notices for a list
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawPeopletagNoticeStream extends NoticeStream
{
    protected $profile_list;

    function __construct($profile_list)
    {
        $this->profile_list = $profile_list;
    }

    /**
     * Query notices by users associated with this tag from the database.
     *
     * @param integer $offset   offset
     * @param integer $limit    maximum no of results
     * @param integer $since_id=null    since this id
     * @param integer $max_id=null  maximum id in result
     *
     * @return array array of notice ids.
     */

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->selectAdd();
        $notice->selectAdd('notice.id');

        $ptag = new Profile_tag();
        $ptag->tag    = $this->profile_list->tag;
        $ptag->tagger = $this->profile_list->tagger;
        $notice->joinAdd(array('profile_id', 'profile_tag:tagged'));
        $notice->whereAdd('profile_tag.tagger = ' . $this->profile_list->tagger);
        $notice->whereAdd(sprintf('profile_tag.tag = "%s"', $this->profile_list->tag));

        if ($since_id != 0) {
            $notice->whereAdd('notice.id > ' . $since_id);
        }

        if ($max_id != 0) {
            $notice->whereAdd('notice.id <= ' . $max_id);
        }

        $notice->orderBy('notice.id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $ids = array();
        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        return $ids;
    }
}

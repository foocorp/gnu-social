<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices that reference an URL
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

class FileNoticeStream extends ScopingNoticeStream
{
    function __construct($file, Profile $scoped=null)
    {
        parent::__construct(new CachingNoticeStream(new RawFileNoticeStream($file),
                                                    'file:notice-ids:'.$file->id),
                            $scoped);
    }
}

/**
 * Raw stream for a file
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RawFileNoticeStream extends NoticeStream
{
    protected $file = null;

    function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Stream of notices linking to this URL
     *
     * @param integer $offset   Offset to show; default is 0
     * @param integer $limit    Limit of notices to show
     * @param integer $since_id Since this notice
     * @param integer $max_id   Before this notice
     *
     * @return array ids of notices that link to this file
     */
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $f2p = new File_to_post();

        $f2p->selectAdd();
        $f2p->selectAdd('post_id');

        $f2p->file_id = $this->file->id;

        Notice::addWhereSinceId($f2p, $since_id, 'post_id', 'modified');
        Notice::addWhereMaxId($f2p, $max_id, 'post_id', 'modified');

        $f2p->orderBy('modified DESC, post_id DESC');

        if (!is_null($offset)) {
            $f2p->limit($offset, $limit);
        }

        $ids = array();

        if ($f2p->find()) {
            while ($f2p->fetch()) {
                $ids[] = $f2p->post_id;
            }
        }

        return $ids;
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Notice_tag extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_tag';                      // table name
    public $tag;                             // varchar(64)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Hash tags',
            'fields' => array(
                'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'),
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice tagged'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('tag', 'notice_id'),
            'foreign keys' => array(
                'notice_tag_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'notice_tag_created_idx' => array('created'),
                'notice_tag_notice_id_idx' => array('notice_id'),
                'notice_tag_tag_created_notice_id_idx' => array('tag', 'created', 'notice_id')
            ),
        );
    }
    
    static function getStream($tag, $offset=0, $limit=20, $sinceId=0, $maxId=0)
    {
        $stream = new TagNoticeStream($tag);
        
        return $stream->getNotices($offset, $limit, $sinceId, $maxId);
    }

    function blowCache($blowLast=false)
    {
        self::blow('notice_tag:notice_ids:%s', Cache::keyize($this->tag));
        if ($blowLast) {
            self::blow('notice_tag:notice_ids:%s;last', Cache::keyize($this->tag));
        }
    }

	static function url($tag)
	{
		if (common_config('singleuser', 'enabled')) {
			// regular TagAction isn't set up in 1user mode
			$nickname = User::singleUserNickname();
			$url = common_local_url('showstream',
									array('nickname' => $nickname,
										  'tag' => $tag));
		} else {
			$url = common_local_url('tag', array('tag' => $tag));
		}

		return $url;
	}
}

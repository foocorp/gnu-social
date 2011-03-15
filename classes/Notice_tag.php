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

class Notice_tag extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_tag';                      // table name
    public $tag;                             // varchar(64)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Notice_tag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getStream($tag, $offset=0, $limit=20) {

        $ids = Notice::stream(array('Notice_tag', '_streamDirect'),
                              array($tag),
                              'notice_tag:notice_ids:' . Cache::keyize($tag),
                              $offset, $limit);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($tag, $offset, $limit, $since_id, $max_id)
    {
        $nt = new Notice_tag();

        $nt->tag = $tag;

        $nt->selectAdd();
        $nt->selectAdd('notice_id');

        Notice::addWhereSinceId($nt, $since_id, 'notice_id');
        Notice::addWhereMaxId($nt, $max_id, 'notice_id');

        $nt->orderBy('created DESC, notice_id DESC');

        if (!is_null($offset)) {
            $nt->limit($offset, $limit);
        }

        $ids = array();

        if ($nt->find()) {
            while ($nt->fetch()) {
                $ids[] = $nt->notice_id;
            }
        }

        return $ids;
    }

    function blowCache($blowLast=false)
    {
        self::blow('notice_tag:notice_ids:%s', Cache::keyize($this->tag));
        if ($blowLast) {
            self::blow('notice_tag:notice_ids:%s;last', Cache::keyize($this->tag));
        }
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Notice_tag', $kv);
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

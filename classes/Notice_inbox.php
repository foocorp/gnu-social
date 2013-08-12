<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

// We keep 5 pages of inbox notices in memcache, +1 for pagination check

define('INBOX_CACHE_WINDOW', 101);
define('NOTICE_INBOX_GC_BOXCAR', 128);
define('NOTICE_INBOX_GC_MAX', 12800);
define('NOTICE_INBOX_LIMIT', 1000);
define('NOTICE_INBOX_SOFT_LIMIT', 1000);

class Notice_inbox extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_inbox';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null
    public $source;                          // tinyint(1)   default_1

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Obsolete; old entries here are converted to packed entries in the inbox table since 0.9',
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the message'),
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
                'source' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'reason it is in the inbox, 1=subscription'),
            ),
            'primary key' => array('user_id', 'notice_id'),
            'foreign keys' => array(
                'notice_inbox_user_id_fkey' => array('user', array('user_id' => 'id')),
                'notice_inbox_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'notice_inbox_notice_id_idx' => array('notice_id'),
            ),
        );
    }

    function stream($user_id, $offset, $limit, $since_id, $max_id, $own=false)
    {
        throw new Exception('Notice_inbox no longer used; use Inbox');
    }

    function _streamDirect($user_id, $own, $offset, $limit, $since_id, $max_id)
    {
        throw new Exception('Notice_inbox no longer used; use Inbox');
    }

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Notice_inbox', $kv);
    }

    static function gc($user_id)
    {
        throw new Exception('Notice_inbox no longer used; use Inbox');
    }

    static function deleteMatching($user_id, $notices)
    {
        throw new Exception('Notice_inbox no longer used; use Inbox');
    }

    static function bulkInsert($notice_id, $created, $ni)
    {
        throw new Exception('Notice_inbox no longer used; use Inbox');
    }
}

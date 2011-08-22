<?php
/**
 * Table Definition for group_alias
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Group_alias extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_alias';                     // table name
    public $alias;                           // varchar(64)  primary_key not_null
    public $group_id;                        // int(4)   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Group_alias',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'alias' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'additional nickname for the group'),
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group profile is blocked from'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date alias was created'),
            ),
            'primary key' => array('alias'),
            'foreign keys' => array(
                'group_alias_group_id_fkey' => array('user_group', array('group_id' => 'id')),
            ),
            'indexes' => array(
                'group_alias_group_id_idx' => array('group_id'),
            ),
        );
    }
}

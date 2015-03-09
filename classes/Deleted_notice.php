<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Table Definition for notice
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Deleted_notice extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'deleted_notice';                  // table name
    public $id;                              // int(4)  primary_key not_null
    public $profile_id;                      // int(4)   not_null
    public $uri;                             // varchar(191)  unique_key   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null
    public $deleted;                         // datetime()   not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'identity of notice'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'author of the notice'),
                'uri' => array('type' => 'varchar', 'length' => 191, 'description' => 'universally unique identifier, usually a tag URI'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),
                'deleted' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'deleted_notice_uri_key' => array('uri'),
            ),
            'indexes' => array(
                'deleted_notice_profile_id_idx' => array('profile_id'),
            ),
        );
    }
}

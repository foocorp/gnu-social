<?php
/*
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Table Definition for profile_role
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_role extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_role';                    // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $role;                            // varchar(32)  primary_key not_null
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile_role',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'account having the role'),
                'role' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'string representing the role'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the role was granted'),
            ),
            'primary key' => array('profile_id', 'role'),
            'foreign keys' => array(
                'profile_role_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array('profile_role_role_created_profile_id_idx' => array('role', 'created', 'profile_id')),
        );
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_role', $kv);
    }

    const OWNER         = 'owner';
    const MODERATOR     = 'moderator';
    const ADMINISTRATOR = 'administrator';
    const SANDBOXED     = 'sandboxed';
    const SILENCED      = 'silenced';
    const DELETED       = 'deleted'; // Pending final deletion of notices...

    public static function isValid($role)
    {
        // @fixme could probably pull this from class constants
        $known = array(self::OWNER,
                       self::MODERATOR,
                       self::ADMINISTRATOR,
                       self::SANDBOXED,
                       self::SILENCED);
        return in_array($role, $known);
    }

    public static function isSettable($role)
    {
        $allowedRoles = array('administrator', 'moderator');
        return self::isValid($role) && in_array($role, $allowedRoles);
    }
}

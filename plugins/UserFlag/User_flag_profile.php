<?php
/**
 * Data class for profile flags
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Data class for profile flags
 *
 * A class representing a user flagging another profile for review.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class User_flag_profile extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_flag_profile';               // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $cleared;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('User_flag_profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * return table definition for DB_DataObject
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array(
                     'profile_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'user_id'    => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'created'    => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'cleared'    => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
                     );
    }

    /**
     * return key definitions for DB_DataObject
     *
     * @return array of key names
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for DB_DataObject
     *
     * @return array map of key definitions
     */
    function keyTypes()
    {
        return array('profile_id' => 'K', 'user_id' => 'K');
    }

    /**
     * Magic formula for non-autoincrementing integer primary keys
     *
     * If a table has a single integer column as its primary key, DB_DataObject
     * assumes that the column is auto-incrementing and makes a sequence table
     * to do this incrementation. Since we don't need this for our class, we
     * overload this method and return the magic formula that DB_DataObject needs.
     *
     * @return array magic three-false array that stops auto-incrementing.
     */
    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Get a single object with multiple keys
     *
     * @param array $kv Map of key-value pairs
     *
     * @return User_flag_profile found object or null
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('User_flag_profile', $kv);
    }

    /**
     * Check if a flag exists for given profile and user
     *
     * @param integer $profile_id Profile to check for
     * @param integer $user_id    User to check for
     *
     * @return boolean true if exists, else false
     */
    static function exists($profile_id, $user_id)
    {
        $ufp = User_flag_profile::pkeyGet(array('profile_id' => $profile_id,
                                                'user_id' => $user_id));

        return !empty($ufp);
    }

    /**
     * Create a new flag
     *
     * @param integer $user_id    ID of user who's flagging
     * @param integer $profile_id ID of profile being flagged
     *
     * @return boolean success flag
     */
    static function create($user_id, $profile_id)
    {
        $ufp = new User_flag_profile();

        $ufp->profile_id = $profile_id;
        $ufp->user_id    = $user_id;
        $ufp->created    = common_sql_now();

        if (!$ufp->insert()) {
            // TRANS: Server exception.
            // TRANS: %d is a profile ID (number).
            $msg = sprintf(_m('Could not flag profile "%d" for review.'),
                           $profile_id);
            throw new ServerException($msg);
        }

        $ufp->free();

        return true;
    }
}

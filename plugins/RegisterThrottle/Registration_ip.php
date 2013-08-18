<?php
/**
 * Data class for storing IP addresses of new registrants.
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
 * Copyright (C) 2010, StatusNet, Inc.
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
 * Data class for storing IP addresses of new registrants.
 *
 * @category Spam
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class Registration_ip extends Managed_DataObject
{
    public $__table = 'registration_ip';     // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $ipaddress;                       // varchar(15)
    public $created;                         // timestamp

    /**
     * return table definition for DB_DataObject
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array('user_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'ipaddress' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'created' => DB_DATAOBJECT_MYSQLTIMESTAMP + DB_DATAOBJECT_NOTNULL);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */
    function keys()
    {
        return array('user_id' => 'K');
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */
    function keyTypes()
    {
        return $this->keys();
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
     * Get the users who've registered with this ip address.
     *
     * @param Array $ipaddress IP address to check for
     *
     * @return Array IDs of users who registered with this address.
     */
    static function usersByIP($ipaddress)
    {
        $ids = array();

        $ri            = new Registration_ip();
        $ri->ipaddress = $ipaddress;

        if ($ri->find()) {
            while ($ri->fetch()) {
                $ids[] = $ri->user_id;
            }
        }

        return $ids;
    }
}

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
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user id this registration relates to'),
                'ipaddress' => array('type' => 'varchar', 'length' => 45, 'description' => 'IP address, max 45+null in IPv6'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'registration_ip_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
            'indexes' => array(
                'registration_ip_ipaddress_idx' => array('ipaddress'),
                'registration_ip_created_idx' => array('created'),
            ),
        );
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

<?php
/**
 * Data class for counting greetings
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
 * Data class for counting greetings
 *
 * We use the DB_DataObject framework for data classes in StatusNet. Each
 * table maps to a particular data class, making it easier to manipulate
 * data.
 *
 * Data classes should extend Memcached_DataObject, the (slightly misnamed)
 * extension of DB_DataObject that provides caching, internationalization,
 * and other bits of good functionality to StatusNet-specific data classes.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class User_followeveryone_prefs extends Managed_DataObject
{
    public $__table = 'user_followeveryone_prefs'; // table name
    public $user_id;                               // int(4)  primary_key not_null
    public $followeveryone;                        // tinyint(1)
    public $created;                               // datetime()   not_null
    public $modified;                              // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user id'),
                'followeveryone' => array('type' => 'int', 'default' => 1, 'size' => 'tiny', 'description' => 'whether to follow everyone'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'user_followeveryone_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    static function followEveryone($user_id)
    {
        $ufep = self::getKV('user_id', $user_id);

        if (empty($ufep)) {
            return true;
        } else {
            return (bool)$ufep->followeveryone;
        }
    }

    static function savePref($user_id, $followEveryone)
    {
        $ufep = self::getKV('user_id', $user_id);

        if (empty($ufep)) {
            $ufep = new User_followeveryone_prefs();
            $ufep->user_id = $user_id;
            $ufep->followeveryone = $followEveryone;
            $ufep->insert();
        } else {
            $orig = clone($ufep);
            $ufep->followeveryone = $followEveryone;
            $ufep->update();
        }

        return true;
    }
}

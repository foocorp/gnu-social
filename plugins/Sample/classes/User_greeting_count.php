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
class User_greeting_count extends Managed_DataObject
{
    public $__table = 'user_greeting_count'; // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $greeting_count;                  // int(4)
    public $created;                         // datetime()   not_null
    public $modified;                        // datetime   not_null default_0000-00-00%2000%3A00%3A00

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user id'),
                'greeting_count' => array('type' => 'int', 'not null' => true, 'description' => 'the greeting count'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'user_greeting_count_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    /**
     * Increment a user's greeting count and return instance
     *
     * This method handles the ins and outs of creating a new greeting_count for a
     * user or fetching the existing greeting count and incrementing its value.
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return User_greeting_count instance for this user, with count already incremented.
     */
    static function inc($user_id)
    {
        $gc = User_greeting_count::getKV('user_id', $user_id);

        if (empty($gc)) {
            $gc = new User_greeting_count();

            $gc->user_id        = $user_id;
            $gc->greeting_count = 1;

            $result = $gc->insert();

            if (!$result) {
                // TRANS: Exception thrown when the user greeting count could not be saved in the database.
                // TRANS: %d is a user ID (number).
                throw Exception(sprintf(_m('Could not save new greeting count for %d.'),
                                        $user_id));
            }
        } else {
            $orig = clone($gc);

            $gc->greeting_count++;

            $result = $gc->update($orig);

            if (!$result) {
                // TRANS: Exception thrown when the user greeting count could not be saved in the database.
                // TRANS: %d is a user ID (number).
                throw Exception(sprintf(_m('Could not increment greeting count for %d.'),
                                        $user_id));
            }
        }

        return $gc;
    }
}

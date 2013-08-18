<?php
/**
 * Data class for favorites talley
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
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
 * Data class for favorites tally
 *
 * A class representing a total number of times a notice has been favored
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class Fave_tally extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave_tally';          // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $count;                           // int(4)  not_null
    public $modified;                        // datetime   not_null default_0000-00-00%2000%3A00%3A00

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
            'notice_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
            'count'     => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
            'modified'  => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL
        );
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has, since it
     * won't appear in StatusNet's own keys list. In most cases, this will
     * simply reference your keyTypes() function.
     *
     * @return array list of key field names
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them. This key information is used to store and clear
     * cached data, so be sure to list any key that will be used for static
     * lookups.
     *
     * @return array associative array of key definitions, field name to type:
     *         'K' for primary key: for compound keys, add an entry for each component;
     *         'U' for unique keys: compound keys are not well supported here.
     */
    function keyTypes()
    {
        return array('notice_id' => 'K');
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
        return Memcached_DataObject::pkeyGet('Fave_tally', $kv);
    }

    /**
     * Increment a notice's tally
     *
     * @param integer $noticeID ID of notice we're tallying
     *
     * @return Fave_tally $tally the tally data object
     */
    static function increment($noticeID)
    {
        $tally = Fave_tally::ensureTally($noticeID);

        $orig = clone($tally);
        $tally->count++;
        $result = $tally->update($orig);

        if (!$result) {
            $msg = sprintf(
                // TRANS: Server exception.
                // TRANS: %d is the notice ID (number).
                _m("Could not update favorite tally for notice ID %d."),
                $noticeID
            );
            throw new ServerException($msg);
        }

        return $tally;
    }

    /**
     * Decrement a notice's tally
     *
     * @param integer $noticeID ID of notice we're tallying
     *
     * @return Fave_tally $tally the tally data object
     */
    static function decrement($noticeID)
    {
        $tally = Fave_tally::ensureTally($noticeID);

        if ($tally->count > 0) {
            $orig = clone($tally);
            $tally->count--;
            $result = $tally->update($orig);

            if (!$result) {
                $msg = sprintf(
                    // TRANS: Server exception.
                    // TRANS: %d is the notice ID (number).
                    _m("Could not update favorite tally for notice ID %d."),
                    $noticeID
                );
                throw new ServerException($msg);
            }
        }

        return $tally;
    }

    /**
     * Ensure a tally exists for a given notice. If we can't find
     * one create one with the total number of existing faves
     *
     * @param integer $noticeID
     *
     * @return Fave_tally the tally data object
     */
    static function ensureTally($noticeID)
    {
        $tally = Fave_tally::staticGet('notice_id', $noticeID);

        if (!$tally) {
            $tally = new Fave_tally();
            $tally->notice_id = $noticeID;
            $tally->count = Fave_tally::countExistingFaves($noticeID);
            $result = $tally->insert();
            if (!$result) {
                $msg = sprintf(
                    // TRANS: Server exception.
                    // TRANS: %d is the notice ID (number).
                    _m("Could not create favorite tally for notice ID %d."),
                    $noticeID
                );
                throw new ServerException($msg);
            }
        }

        return $tally;
    }

    /**
     * Count the number of faves a notice already has. Used to initalize
     * a tally for a notice.
     *
     * @param integer $noticeID ID of the notice to count faves for
     *
     * @return integer $total total number of time the notice has been favored
     */
    static function countExistingFaves($noticeID)
    {
        $fave = new Fave();
        $fave->notice_id = $noticeID;
        $total = $fave->count();
        return $total;
    }
}

<?php
/**
 * Data class for email summary status
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
 * Data class for email summaries
 *
 * Email summary information for users
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Email_summary_status extends Managed_DataObject
{
    public $__table = 'email_summary_status'; // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $send_summary;                    // tinyint not_null
    public $last_summary_id;                 // int(4)  null
    public $created;                         // datetime not_null
    public $modified;                        // datetime not_null

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array('user_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'send_summary' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'last_summary_id' => DB_DATAOBJECT_INT,
                     'created' => DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    /**
     * return key definitions for DB_DataObject
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
        return array('user_id' => 'K');
    }

    /**
     * Magic formula for non-autoincrementing integer primary keys
     *
     * @return array magic three-false array that stops auto-incrementing.
     */
    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Helper function
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return int flag for whether to send this user a summary email
     */
    static function getSendSummary($user_id)
    {
        $ess = Email_summary_status::staticGet('user_id', $user_id);

        if (!empty($ess)) {
            return $ess->send_summary;
        } else {
            return 1;
        }
    }

    /**
     * Get email summary status for a user
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return Email_summary_status instance for this user, with count already incremented.
     */
    static function getLastSummaryID($user_id)
    {
        $ess = Email_summary_status::staticGet('user_id', $user_id);

        if (!empty($ess)) {
            return $ess->last_summary_id;
        } else {
            return null;
        }
    }
}

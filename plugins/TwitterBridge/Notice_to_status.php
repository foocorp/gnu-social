<?php
/**
 * Data class for remembering notice-to-status mappings
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
 * Data class for mapping notices to statuses
 *
 * Notices flow back and forth between Twitter and StatusNet. We use this
 * table to remember which StatusNet notice corresponds to which Twitter
 * status.
 *
 * Note that notice_id is unique only within a single database; if you
 * want to share this data for some reason, get the notice's URI and use
 * that instead, since it's universally unique.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Notice_to_status extends Managed_DataObject
{
    public $__table = 'notice_to_status'; // table name
    public $notice_id;                    // int(4)  primary_key not_null
    public $status_id;                    // int(4)
    public $created;                      // datetime

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
        return array('notice_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'status_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
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
        return array('notice_id' => 'K', 'status_id' => 'U');
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
     * Save a mapping between a notice and a status
     * Warning: status_id values may not fit in 32-bit integers.
     *
     * @param integer $notice_id ID of the notice in StatusNet
     * @param integer $status_id ID of the status in Twitter
     *
     * @return Notice_to_status new object for this value
     */
    static function saveNew($notice_id, $status_id)
    {
        if (empty($notice_id)) {
            throw new Exception("Invalid notice_id $notice_id");
        }
        $n2s = Notice_to_status::staticGet('notice_id', $notice_id);

        if (!empty($n2s)) {
            return $n2s;
        }

        if (empty($status_id)) {
            throw new Exception("Invalid status_id $status_id");
        }
        $n2s = Notice_to_status::staticGet('status_id', $status_id);

        if (!empty($n2s)) {
            return $n2s;
        }

        common_debug("Mapping notice {$notice_id} to Twitter status {$status_id}");

        $n2s = new Notice_to_status();

        $n2s->notice_id = $notice_id;
        $n2s->status_id = $status_id;
        $n2s->created   = common_sql_now();

        $n2s->insert();

        return $n2s;
    }
}

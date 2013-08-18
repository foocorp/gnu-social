<?php
/**
 * Store last-touched ID for various timelines
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
 * Store various timeline data
 *
 * We don't want to keep re-fetching the same statuses and direct messages from Twitter.
 * So, we store the last ID we see from a timeline, and store it. Next time
 * around, we use that ID in the since_id parameter.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Twitter_synch_status extends Managed_DataObject
{
    public $__table = 'twitter_synch_status'; // table name
    public $foreign_id;                         // int(4)  primary_key not_null
    public $timeline;                        // varchar(255)  primary_key not_null
    public $last_id;                         // bigint not_null
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
        return array('foreign_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'timeline' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'last_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL
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
        return array('foreign_id' => 'K',
                     'timeline' => 'K');
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

    static function getLastId($foreign_id, $timeline)
    {
        $tss = self::pkeyGet(array('foreign_id' => $foreign_id,
                                   'timeline' => $timeline));

        if (empty($tss)) {
            return null;
        } else {
            return $tss->last_id;
        }
    }

    static function setLastId($foreign_id, $timeline, $last_id)
    {
        $tss = self::pkeyGet(array('foreign_id' => $foreign_id,
                                   'timeline' => $timeline));

        if (empty($tss)) {
            $tss = new Twitter_synch_status();

            $tss->foreign_id = $foreign_id;
            $tss->timeline   = $timeline;
            $tss->last_id    = $last_id;
            $tss->created    = common_sql_now();
            $tss->modified   = $tss->created;

            $tss->insert();

            return true;
        } else {
            $orig = clone($tss);

            $tss->last_id  = $last_id;
            $tss->modified = common_sql_now();

            $tss->update();

            return true;
        }
    }
}

<?php
/**
 * Data class for remembering Yammer import mappings
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
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

/**
 * Temporary storage for imported Yammer messages between fetching and saving
 * as local notices.
 *
 * The Yammer API only allows us to page down from the most recent items; in
 * order to start saving the oldest notices first, we have to pull them all
 * down in reverse chronological order, then go back over them from oldest to
 * newest and actually save them into our notice table.
 */
class Yammer_notice_stub extends Managed_DataObject
{
    public $__table = 'yammer_notice_stub'; // table name
    public $id;                             // int  primary_key not_null
    public $json_data;                      // text
    public $created;                        // datetime

    /**
     * Return schema definition to set this table up in onCheckSchema
     */
    static function schemaDef()
    {
        return array(new ColumnDef('id', 'bigint', null,
                                   false, 'PRI'),
                     new ColumnDef('json_data', 'text', null,
                                   false),
                     new ColumnDef('created', 'datetime', null,
                                   false));
    }

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
        return array('id'           => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'json_data'    => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'created'      => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
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
        return array('id' => 'K');
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
     * Decode the stored data structure.
     *
     * @return mixed
     */
    public function getData()
    {
        return json_decode($this->json_data, true);
    }

    /**
     * Save the native Yammer API representation of a message for the pending
     * import. Since they come in in reverse chronological order, we need to
     * record them all as stubs and then go through from the beginning and
     * save them as native notices, or we'll lose ordering and threading
     * data.
     *
     * @param integer $orig_id ID of the notice on Yammer
     * @param array $data the message record fetched out of Yammer API returnd data
     *
     * @return Yammer_notice_stub new object for this value
     */

    static function record($orig_id, $data)
    {
        common_debug("Recording Yammer message stub {$orig_id} for pending import...");

        $stub = new Yammer_notice_stub();

        $stub->id = $orig_id;
        $stub->json_data = json_encode($data);
        $stub->created = common_sql_now();

        $stub->insert();

        return $stub;
    }
}

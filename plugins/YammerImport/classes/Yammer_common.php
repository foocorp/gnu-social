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
 * Common base class for the Yammer import mappings for users, groups, and notices.
 *
 * Child classes must override these static methods, since we need to run
 * on PHP 5.2.x which has no late static binding: (not really anymore)
 * - staticGet (as our other classes)
 * - schemaDef (call self::doSchemaDef)
 * - record (call self::doRecord)
 */
class Yammer_common extends Managed_DataObject
{
    public $__table = 'yammer_XXXX'; // table name
    public $__field = 'XXXX_id';     // field name to save into
    public $id;                      // int  primary_key not_null
    public $user_id;                 // int(4)
    public $created;                 // datetime

    /**
     * @todo FIXME: Add a 'references' thing for the foreign key when we support that
     */
    protected static function doSchemaDef($field)
    {
        return array(new ColumnDef('id', 'bigint', null,
                                   false, 'PRI'),
                     new ColumnDef($field, 'integer', null,
                                   false, 'UNI'),
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
                     $this->__field => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
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
        return array('id' => 'K', $this->__field => 'U');
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
     * Save a mapping between a remote Yammer and local imported user.
     *
     * @param integer $user_id ID of the status in StatusNet
     * @param integer $orig_id ID of the notice in Yammer
     *
     * @return Yammer_common new object for this value
     */
    protected static function doRecord($class, $field, $orig_id, $local_id)
    {
        $map = Memcached_DataObject::staticGet($class, 'id', $orig_id);

        if (!empty($map)) {
            return $map;
        }

        $map = Memcached_DataObject::staticGet($class, $field, $local_id);

        if (!empty($map)) {
            return $map;
        }

        common_debug("Mapping Yammer $field {$orig_id} to local $field {$local_id}");

        $map = new $class();

        $map->id = $orig_id;
        $map->$field = $local_id;
        $map->created = common_sql_now();

        $map->insert();

        return $map;
    }
}

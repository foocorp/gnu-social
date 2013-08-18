<?php
/**
 * Data class for remembering Yammer import state
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

class Yammer_state extends Managed_DataObject
{
    public $__table = 'yammer_state'; // table name
    public $id;                       // int  primary_key not_null
    public $state;                    // import state key
    public $last_error;               // text of last-encountered error, if any
    public $oauth_token;              // actual oauth token! clear when import is done?
    public $oauth_secret;             // actual oauth secret! clear when import is done?
    public $users_page;               // last page of users we've fetched
    public $groups_page;              // last page of groups we've fetched
    public $messages_oldest;          // oldest message ID we've fetched
    public $messages_newest;          // newest message ID we've imported
    public $created;                  // datetime
    public $modified;                 // datetime

    /**
     * Return schema definition to set this table up in onCheckSchema
     */
    static function schemaDef()
    {
        return array(new ColumnDef('id', 'int', null,
                                   false, 'PRI'),
                     new ColumnDef('state', 'text'),
                     new ColumnDef('last_error', 'text'),
                     new ColumnDef('oauth_token', 'text'),
                     new ColumnDef('oauth_secret', 'text'),
                     new ColumnDef('users_page', 'int'),
                     new ColumnDef('groups_page', 'int'),
                     new ColumnDef('messages_oldest', 'bigint'),
                     new ColumnDef('messages_newest', 'bigint'),
                     new ColumnDef('created', 'datetime'),
                     new ColumnDef('modified', 'datetime'));
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
        return array('id'              => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'state'           => DB_DATAOBJECT_STR,
                     'last_error'      => DB_DATAOBJECT_STR,
                     'oauth_token'     => DB_DATAOBJECT_STR,
                     'oauth_secret'    => DB_DATAOBJECT_STR,
                     'users_page'      => DB_DATAOBJECT_INT,
                     'groups_page'     => DB_DATAOBJECT_INT,
                     'messages_oldest' => DB_DATAOBJECT_INT,
                     'messages_newest' => DB_DATAOBJECT_INT,
                     'created'         => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
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
}

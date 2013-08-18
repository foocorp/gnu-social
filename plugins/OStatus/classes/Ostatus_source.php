<?php
/*
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class Ostatus_source extends Managed_DataObject
{
    public $__table = 'ostatus_source';

    public $notice_id; // notice we're referring to
    public $profile_uri; // uri of the ostatus_profile this came through -- may be a group feed
    public $method; // push or salmon

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
                     'profile_uri' => DB_DATAOBJECT_STR,
                     'method' => DB_DATAOBJECT_STR);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('notice_id', 'integer',
                                   null, false, 'PRI'),
                     new ColumnDef('profile_uri', 'varchar',
                                   255, false),
                     new ColumnDef('method', "ENUM('push','salmon')",
                                   null, false));
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */
    function keyTypes()
    {
        return array('notice_id' => 'K');
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Save a remote notice source record; this helps indicate how trusted we are.
     * @param string $method
     */
    public static function saveNew(Notice $notice, Ostatus_profile $oprofile, $method)
    {
        $osource = new Ostatus_source();
        $osource->notice_id = $notice->id;
        $osource->profile_uri = $oprofile->uri;
        $osource->method = $method;
        if ($osource->insert()) {
           return true;
        } else {
            common_log_db_error($osource, 'INSERT', __FILE__);
            return false;
        }
    }
}

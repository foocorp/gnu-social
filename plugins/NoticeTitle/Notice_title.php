<?php
/**
 * Data class for notice titles
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
 * Data class for notice titles
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Notice_title extends Managed_DataObject
{
    const MAXCHARS = 255;

    public $__table = 'notice_title'; // table name
    public $notice_id;                         // int(4)  primary_key not_null
    public $title;                             // varchar(255)

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
                     'title' => DB_DATAOBJECT_STR);
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
     * @return array list mapping field names to key types
     */
    function keyTypes()
    {
        return array('notice_id' => 'K');
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
     * Get a notice title based on the notice
     *
     * @param Notice $notice Notice to fetch a title for
     *
     * @return string title of the notice, or null if none
     */
    static function fromNotice($notice)
    {
        $nt = Notice_title::staticGet('notice_id', $notice->id);
        if (empty($nt)) {
            return null;
        } else {
            return $nt->title;
        }
    }
}

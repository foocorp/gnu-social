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

class Yammer_group extends Yammer_common
{
    public $__table = 'yammer_group'; // table name
    public $__field = 'group_id';     // field to map to
    public $group_id;                 // int

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup
     * @param mixed  $v Value to lookup
     *
     * @return Yammer_group object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Yammer_group', $k, $v);
    }

    /**
     * Return schema definition to set this table up in onCheckSchema
     */
    static function schemaDef()
    {
        return self::doSchemaDef('group_id');
    }

    /**
     * Save a mapping between a remote Yammer and local imported group.
     *
     * @param integer $orig_id ID of the notice in Yammer
     * @param integer $group_id ID of the status in StatusNet
     *
     * @return Yammer_group new object for this value
     */
    static function record($orig_id, $group_id)
    {
        return self::doRecord('Yammer_group', 'group_id', $orig_id, $group_id);
    }
}

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

class Yammer_notice extends Yammer_common
{
    public $__table = 'yammer_notice'; // table name
    public $__field = 'notice_id';     // field to map to
    public $notice_id;                 // int

    /**
     * Return schema definition to set this table up in onCheckSchema
     */
    static function schemaDef()
    {
        return self::doSchemaDef('notice_id');
    }

    /**
     * Save a mapping between a remote Yammer and local imported notice.
     *
     * @param integer $orig_id ID of the notice in Yammer
     * @param integer $notice_id ID of the status in StatusNet
     *
     * @return Yammer_notice new object for this value
     */
    static function record($orig_id, $notice_id)
    {
        return self::doRecord('Yammer_notice', 'notice_id', $orig_id, $notice_id);
    }
}

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
    public $notice_id;                         // int(11)  primary_key not_null
    public $title;                             // varchar(255)
    public $created;                           // datetime()   not_null
    public $modified;                          // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice id this title relates to'),
                'title' => array('type' => 'varchar', 'length' => Notice_title::MAXCHARS, 'description' => 'title to notice'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id'),
            'foreign keys' => array(
                'notice_title_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
        );
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
        $nt = Notice_title::getKV('notice_id', $notice->id);
        if (empty($nt)) {
            return null;
        } else {
            return $nt->title;
        }
    }
}

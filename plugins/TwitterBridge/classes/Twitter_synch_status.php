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
    public $foreign_id;                      // bigint primary_key not_null
    public $timeline;                        // varchar(255)  primary_key not_null
    public $last_id;                         // bigint not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'foreign_id' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'Foreign message ID'),
                'timeline' => array('type' => 'varchar', 'length' => 255, 'description' => 'timeline name'),
                'last_id' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'last id fetched'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('foreign_id', 'timeline'),
        );
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

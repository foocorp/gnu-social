<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A channel for real-time browser data
 *
 * PHP version 5
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
 *
 * @category  Realtime
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * A channel for real-time browser data
 *
 * For each user currently browsing the site, we want to know which page they're on
 * so we can send real-time updates to their browser.
 *
 * @category Realtime
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Realtime_channel extends Managed_DataObject
{
    const TIMEOUT = 1800; // 30 minutes

    public $__table = 'realtime_channel'; // table name

    public $user_id;       // int -> user.id, can be null
    public $action;        // varchar(191)                  not 255 because utf8mb4 takes more space
    public $arg1;          // varchar(191)   argument       not 255 because utf8mb4 takes more space
    public $arg2;          // varchar(191)   usually null   not 255 because utf8mb4 takes more space
    public $channel_key;   // 128-bit shared secret key
    public $audience;      // listener count
    public $created;       // created date
    public $modified;      // modified date

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'A channel of realtime notice data',
            'fields' => array(
                'user_id' => array('type' => 'int',
                                   'not null' => false,
                                   'description' => 'user viewing page; can be null'),
                'action' => array('type' => 'varchar',
                                  'length' => 191,
                                  'not null' => true,
                                  'description' => 'page being viewed'),
                'arg1' => array('type' => 'varchar',
                                'length' => 191,
                                'not null' => false,
                                'description' => 'page argument, like username or tag'),
                'arg2' => array('type' => 'varchar',
                                'length' => 191,
                                'not null' => false,
                                'description' => 'second page argument, like tag for showstream'),
                'channel_key' => array('type' => 'varchar',
                               'length' => 32,
                               'not null' => true,
                               'description' => 'shared secret key for this channel'),
                'audience' => array('type' => 'integer',
                                    'not null' => true,
                                    'default' => 0,
                                    'description' => 'reference count'),
                'created' => array('type' => 'datetime',
                                   'not null' => true,
                                   'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime',
                                    'not null' => true,
                                    'description' => 'date this record was modified'),
            ),
            'primary key' => array('channel_key'),
            'unique keys' => array('realtime_channel_user_page_idx' => array('user_id', 'action', 'arg1', 'arg2')),
            'foreign keys' => array(
                'realtime_channel_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
            'indexes' => array(
                'realtime_channel_modified_idx' => array('modified'),
                'realtime_channel_page_idx' => array('action', 'arg1', 'arg2')
            ),
        );
    }

    static function saveNew($user_id, $action, $arg1, $arg2)
    {
        $channel = new Realtime_channel();

        $channel->user_id = $user_id;
        $channel->action  = $action;
        $channel->arg1    = $arg1;
        $channel->arg2    = $arg2;
        $channel->audience  = 1;

        $channel->channel_key = common_random_hexstr(16); // 128-bit key, 32 hex chars

        $channel->created  = common_sql_now();
        $channel->modified = $channel->created;

        $channel->insert();

        return $channel;
    }

    static function getChannel($user_id, $action, $arg1, $arg2)
    {
        $channel = self::fetchChannel($user_id, $action, $arg1, $arg2);

        // Ignore (and delete!) old channels

        if (!empty($channel)) {
            $modTime = strtotime($channel->modified);
            if ((time() - $modTime) > self::TIMEOUT) {
                $channel->delete();
                $channel = null;
            }
        }

        if (empty($channel)) {
            $channel = self::saveNew($user_id, $action, $arg1, $arg2);
        }

        return $channel;
    }

    static function getAllChannels($action, $arg1, $arg2)
    {
        $channel = new Realtime_channel();

        $channel->action = $action;

        if (is_null($arg1)) {
            $channel->whereAdd('arg1 is null');
        } else {
            $channel->arg1 = $arg1;
        }

        if (is_null($arg2)) {
            $channel->whereAdd('arg2 is null');
        } else {
            $channel->arg2 = $arg2;
        }

        $channel->whereAdd('modified > "' . common_sql_date(time() - self::TIMEOUT) . '"');

        $channels = array();

        if ($channel->find()) {
            $channels = $channel->fetchAll();
        }

        return $channels;
    }

    static function fetchChannel($user_id, $action, $arg1, $arg2)
    {
        $channel = new Realtime_channel();

        if (is_null($user_id)) {
            $channel->whereAdd('user_id is null');
        } else {
            $channel->user_id = $user_id;
        }

        $channel->action = $action;

        if (is_null($arg1)) {
            $channel->whereAdd('arg1 is null');
        } else {
            $channel->arg1 = $arg1;
        }

        if (is_null($arg2)) {
            $channel->whereAdd('arg2 is null');
        } else {
            $channel->arg2 = $arg2;
        }

        if ($channel->find(true)) {
            $channel->increment();
            return $channel;
        } else {
            return null;
        }
    }

    function increment()
    {
        // XXX: race
        $orig = clone($this);
        $this->audience++;
        $this->modified = common_sql_now();
        $this->update($orig);
    }

    function touch()
    {
        // XXX: race
        $orig = clone($this);
        $this->modified = common_sql_now();
        $this->update($orig);
    }

    function decrement()
    {
        // XXX: race
        if ($this->audience == 1) {
            $this->delete();
        } else {
            $orig = clone($this);
            $this->audience--;
            $this->modified = common_sql_now();
            $this->update($orig);
        }
    }
}

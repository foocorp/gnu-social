<?php
/**
 * Data class for email summary status
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
 * Data class for email summaries
 *
 * Email summary information for users
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Email_summary_status extends Managed_DataObject
{
    public $__table = 'email_summary_status'; // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $send_summary;                    // tinyint not_null
    public $last_summary_id;                 // int(4)  null
    public $created;                         // datetime not_null
    public $modified;                        // datetime not_null

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user id'),
                'send_summary' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'not null' => true, 'description' => 'whether to send a summary or not'),
                'last_summary_id' => array('type' => 'int', 'description' => 'last summary id'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),   
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'email_summary_status_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    /**
     * Helper function
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return int flag for whether to send this user a summary email
     */
    static function getSendSummary($user_id)
    {
        $ess = Email_summary_status::getKV('user_id', $user_id);

        if (!empty($ess)) {
            return $ess->send_summary;
        } else {
            return 1;
        }
    }

    /**
     * Get email summary status for a user
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return Email_summary_status instance for this user, with count already incremented.
     */
    static function getLastSummaryID($user_id)
    {
        $ess = Email_summary_status::getKV('user_id', $user_id);

        if (!empty($ess)) {
            return $ess->last_summary_id;
        } else {
            return null;
        }
    }
}

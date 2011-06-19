<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Data class for email reminders
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
 * @category  Data
 * @package   EmailReminder
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Email_reminder extends Managed_DataObject
{
    const INVITE_REMINDER   = 'invite'; // @todo Move this to the invite reminder handler

    public $__table = 'email_reminder';

    public $type;     // type of reminder
    public $code;     // confirmation code
    public $days;     // number of days after code was created
    public $sent;     // timestamp
    public $created;  // timestamp
    public $modified; // timestamp

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup
     * @param mixed  $v Value to lookup
     *
     * @return QnA_Answer object found, or null for no hits
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('email_reminder', $k, $v);
    }

    /**
     *
     * @param type $type
     * @param type $confirm
     * @param type $day
     * @return type
     */
    static function needsReminder($type, $confirm, $days) {

        $reminder        = new Email_reminder();
        $reminder->type  = $type;
        $reminder->code  = $confirm->code;
        $reminder->days  = $days;

        $result = $reminder->find(true);

        if (empty($result)) {
            return true;
        }

        return false;
    }

    /**
     *
     * @param type $type
     * @param type $confirm
     * @param type $day
     * @return type
     */
    static function recordReminder($type, $confirm, $days) {

        $reminder        = new Email_reminder();
        $reminder->type  = $type;
        $reminder->code  = $confirm->code;
        $reminder->days  = $days;
        $reminder->sent  = $reminder->created = common_sql_now();
        $result          = $reminder->insert();

        if (empty($result)) {
            common_log_db_error($reminder, 'INSERT', __FILE__);
                throw new ServerException(
                    // TRANS: Server exception thrown when a reminder record could not be inserted into the database.
                    _m('Database error inserting reminder record.')
            );
        }

        return $result;
    }

    /**
     * Data definition for email reminders
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Record of email reminders that have been sent',
            'fields'      => array(
                'type'     => array(
                    'type'          => 'varchar',
                    'length'        => 255,
                    'not null'      => true,
                    'description'   => 'type of reminder'
                ),
                'code' => array(
                    'type'        => 'varchar',
                    'not null'    => 'true',
                    'length'      => 255,
                    'description' => 'confirmation code'
                 ),
                'days' => array(
                    'type'        => 'int',
                    'not null'    => 'true',
                    'description' => 'number of days since code creation'
                 ),
                'sent' => array(
                    'type'        => 'datetime',
                    'not null'    => true,
                    'description' => 'Date and time the reminder was sent'
                ),
                'created' => array(
                    'type'        => 'datetime',
                    'not null'    => true,
                    'description' => 'Date and time the record was created'
                ),
                'modified'        => array(
                    'type'        => 'timestamp',
                    'not null'    => true,
                    'description' => 'Date and time the record was last modified'
                ),
            ),
            'primary key' => array('type', 'code', 'days'),
            'indexes' => array(
                'sent_idx' => array('sent'),
             ),
        );
    }
}

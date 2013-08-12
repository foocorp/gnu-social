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
    public $__table = 'email_reminder';

    public $type;     // type of reminder
    public $code;     // confirmation code
    public $days;     // number of days after code was created
    public $sent;     // timestamp
    public $created;  // timestamp
    public $modified; // timestamp

    /**
     * Do we need to send a reminder?
     *
     * @param string $type      type of reminder
     * @param Object $object    an object with a 'code' property
     *                          (Confirm_address or Invitation)
     * @param int    $days      Number of days after the code was created
     * @return boolean true if any Email_reminder records were found
     */
    static function needsReminder($type, $object, $days = null) {

        $reminder        = new Email_reminder();
        $reminder->type  = $type;
        $reminder->code  = $object->code;
        if (!empty($days)) {
            $reminder->days  = $days;
        }
        $result = $reminder->find();

        if (!empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * Record a record of sending the reminder
     *
     * @param string $type      type of reminder
     * @param Object $object    an object with a 'code' property
     *                          (Confirm_address or Invitation)
     * @param int    $days      Number of days after the code was created
     * @return int   $result    row ID of the new reminder record
     */
    static function recordReminder($type, $object, $days) {

        $reminder        = new Email_reminder();
        $reminder->type  = $type;
        $reminder->code  = $object->code;
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

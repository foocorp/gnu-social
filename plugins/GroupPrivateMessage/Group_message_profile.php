<?php
/**
 * Who received a group message
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
 * Copyright (C) 2011, StatusNet, Inc.
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
 * Data class for group direct messages for users
 *
 * @category GroupPrivateMessage
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Group_message_profile extends Managed_DataObject
{
    public $__table = 'group_message_profile'; // table name
    public $to_profile;                        // int
    public $group_message_id;                  // char(36)  primary_key not_null
    public $created;

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
        return array('to_profile' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'group_message_id' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has, since it
     * won't appear in StatusNet's own keys list. In most cases, this will
     * simply reference your keyTypes() function.
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
     * @return array associative array of key definitions, field name to type:
     *         'K' for primary key: for compound keys, add an entry for each component;
     *         'U' for unique keys: compound keys are not well supported here.
     */
    function keyTypes()
    {
        return array('to_profile' => 'K', 'group_message_id' => 'K');
    }

    /**
     * No sequence keys in this table.
     */
    function sequenceKey()
    {
        return array(false, false, false);
    }

    function send($gm, $profile)
    {
        $gmp = new Group_message_profile();

        $gmp->group_message_id = $gm->id;
        $gmp->to_profile       = $profile->id;
        $gmp->created          = common_sql_now();

        $gmp->insert();

        // If it's not for the author, send email notification
        if ($gm->from_profile != $profile->id) {
            $gmp->notify();
        }

        return $gmp;
    }

    function notify()
    {
        // XXX: add more here
        $this->notifyByMail();
    }

    function notifyByMail()
    {
        $to = User::getKV('id', $this->to_profile);

        if (empty($to) || is_null($to->email) || !$to->emailnotifymsg) {
            return true;
        }

        $gm = Group_message::getKV('id', $this->group_message_id);

        $from_profile = Profile::getKV('id', $gm->from_profile);

        $group = $gm->getGroup();

        common_switch_locale($to->language);

        // TRANS: Subject for direct-message notification email.
        // TRANS: %1$s is the sending user's nickname, %2$s is the group nickname.
        $subject = sprintf(_m('New private message from %1$s to group %2$s'), $from_profile->nickname, $group->nickname);

        // TRANS: Body for direct-message notification email.
        // TRANS: %1$s is the sending user's long name, %2$s is the sending user's nickname,
        // TRANS: %3$s is the message content, %4$s a URL to the message,
        // TRANS: %5$s is the StatusNet sitename.
        $body = sprintf(_m("%1\$s (%2\$s) sent a private message to group %3\$s:\n\n".
                           "------------------------------------------------------\n".
                           "%4\$s\n".
                           "------------------------------------------------------\n\n".
                           "You can reply to their message here:\n\n".
                           "%5\$s\n\n".
                           "Do not reply to this email; it will not get to them.\n\n".
                           "With kind regards,\n".
                           "%6\$s"),
                        $from_profile->getBestName(),
                        $from_profile->nickname,
                        $group->nickname,
                        $gm->content,
                        common_local_url('newmessage', array('to' => $from_profile->id)),
                        common_config('site', 'name')) . "\n";

        $headers = _mail_prepare_headers('message', $to->nickname, $from_profile->nickname);

        common_switch_locale();

        return mail_to_user($to, $subject, $body, $headers);
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * Handler for queue items of type 'uregem' - sends email registration
 * confirmation reminders to a particular user.
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
 * @category  Email
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Handler for queue items of type 'uregrem'
 *
 * @category  Email
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class UserConfirmRegReminderHandler extends UserReminderHandler {

    const REGISTER_REMINDER = 'register';

    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */
    function transport() {
        return 'uregrem';
    }

    /**
     * Send an email registration confirmation reminder until the user
     * confirms her registration. We'll send a reminder after one day,
     * three days, and a full week.
     *
     * @todo abstract this bit further
     *
     * @param array $regitem confirmation address and any special options
     * @return boolean success value
     */
    function sendNextReminder($regitem)
    {
        list($confirm, $opts) = $regitem;

        $regDate = strtotime($confirm->modified); // Seems like my best bet
        $now     = strtotime('now');

        // Days since registration
        $days = ($now - $regDate) / 86499; // 60*60*24 = 86499
        // $days = ($now - $regDate) / 120; // Two mins, good for testing

        if ($days > 7 && isset($opts['onetime'])) {
            // Don't send the reminder if we're past the normal reminder window and
            // we've already pestered her at all before
            if (Email_reminder::needsReminder(self::REGISTER_REMINDER, $confirm)) {
                common_log(LOG_INFO, "Sending one-time registration confirmation reminder to {$confirm->address}", __FILE__);
                $subject = _m("Reminder - please confirm your registration!");
                return EmailReminderPlugin::sendReminder(
                    self::REGISTER_REMINDER,
                    $confirm,
                    $subject,
                    -1 // special one-time indicator
                );
            }
        }

        // Welcome to one of the ugliest switch statement I've ever written

        switch($days) {
        case ($days > 1 && $days < 2):
            if (Email_reminder::needsReminder(self::REGISTER_REMINDER, $confirm, 1)) {
                common_log(LOG_INFO, "Sending one day registration confirmation reminder to {$confirm->address}", __FILE__);
                // TRANS: Subject for reminder e-mail.
                $subject = _m('Reminder - please confirm your registration!');
                return EmailReminderPlugin::sendReminder(
                    self::REGISTER_REMINDER,
                    $confirm,
                    $subject,
                    1
                );
            } else {
                return true;
            }
            break;
        case ($days > 3 && $days < 4):
            if (Email_reminder::needsReminder(self::REGISTER_REMINDER, $confirm, 3)) {
                common_log(LOG_INFO, "Sending three day registration confirmation reminder to {$confirm->address}", __FILE__);
                // TRANS: Subject for reminder e-mail.
                $subject = _m('Second reminder - please confirm your registration!');
                    return EmailReminderPlugin::sendReminder(
                        self::REGISTER_REMINDER,
                        $confirm,
                        $subject,
                        3
                    );
                } else {
                    return true;
                }
            break;
        case ($days > 7 && $days < 8):
            if (Email_reminder::needsReminder(self::REGISTER_REMINDER, $confirm, 7)) {
                common_log(LOG_INFO, "Sending one week registration confirmation reminder to {$confirm->address}", __FILE__);
                // TRANS: Subject for reminder e-mail.
                $subject = _m('Final reminder - please confirm your registration!');
                return EmailReminderPlugin::sendReminder(
                    self::REGISTER_REMINDER,
                    $confirm,
                    $subject,
                    7
                );
            } else {
                return true;
            }
            break;
        default:
            common_log(LOG_INFO, "No need to send registration reminder to {$confirm->address}.", __FILE__);
            break;
        }
        return true;
    }
}

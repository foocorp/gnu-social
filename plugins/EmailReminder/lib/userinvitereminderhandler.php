<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * Handler for queue items of type 'uinvrem' - sends an email reminder to
 * an email address of someone who has been invited to the site
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
 * Handler for queue items of type 'uinvrem' (user invite reminder)
 *
 * @category  Email
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class UserInviteReminderHandler extends UserReminderHandler {

    const INVITE_REMINDER = 'invite';

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
        return 'uinvrem';
    }

    /**
     * Send an invitation reminder. We'll send one after one day, and then
     * one after three days.
     *
     * @todo Abstract this stuff further
     *
     * @param array $invitem Invitation obj and any special options
     * @return boolean success value
     */
    function sendNextReminder($invitem)
    {
        list($invitation, $opts) = $invitem;

        $invDate = strtotime($invitation->created);
        $now     = strtotime('now');

        // Days since first invitation was sent
        $days = ($now - $invDate) / 86499; // 60*60*24 = 86499
        // $days = ($now - $regDate) / 120; // Two mins, good for testing

        $siteName = common_config('site', 'name');

        if ($days > 7 && isset($opts['onetime'])) {
            // Don't send the reminder if we're past the normal reminder window and
            // we've already pestered her at all before
            if (Email_reminder::needsReminder(self::INVITE_REMINDER, $invitation)) {
                common_log(LOG_INFO, "Sending one-time invitation reminder to {$invitation->address}", __FILE__);
                $subject = _m("Reminder - you have been invited to join {$siteName}!");
                return EmailReminderPlugin::sendReminder(
                    self::INVITE_REMINDER,
                    $invitation,
                    $subject,
                    -1 // special one-time indicator
                );
            }
        }

        switch($days) {
        case ($days > 1 && $days < 2):
            if (Email_reminder::needsReminder(self::INVITE_REMINDER, $invitation, 1)) {
                common_log(LOG_INFO, "Sending one day invitation reminder to {$invitation->address}", __FILE__);
                // TRANS: Subject for reminder e-mail. %s is the StatusNet sitename.
                $subject = sprintf(_m('Reminder - You have been invited to join %s!'),$siteName);
                return EmailReminderPlugin::sendReminder(
                    self::INVITE_REMINDER,
                    $invitation,
                    $subject,
                1);
            } else {
                return true;
            }
            break;
        case ($days > 3 && $days < 4):
            if (Email_reminder::needsReminder(self::INVITE_REMINDER, $invitation, 3)) {
                common_log(LOG_INFO, "Sending three day invitation reminder to {$invitation->address}", __FILE__);
                // TRANS: Subject for reminder e-mail. %s is the StatusNet sitename.
                $subject = sprintf(_m('Final reminder - you have been invited to join %s!'),$siteName);
                    return EmailReminderPlugin::sendReminder(
                        self::INVITE_REMINDER,
                        $invitation,
                        $subject,
                        3
                    );
                } else {
                    return true;
                }
            break;
        default:
            common_log(LOG_INFO, "No need to send invitation reminder to {$invitation->address}.", __FILE__);
            break;
        }
        return true;
    }
}

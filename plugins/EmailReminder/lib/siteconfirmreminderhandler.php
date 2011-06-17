<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 *
 * Handler for reminder queue items which send reminder emails to all users
 * we would like to complete a given process (e.g.: registration).
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
 * Handler for reminder queue items which send reminder emails to all users
 * we would like to complete a given process (e.g.: registration)
 *
 * @category  Email
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SiteConfirmReminderHandler extends QueueHandler
{
    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */
    function transport()
    {
        return 'siterem';
    }

    /**
     * Handle the site
     *
     * @param string $reminderType type of reminder to send
     * @return boolean true on success, false on failure
     */
    function handle($reminderType)
    {
        $qm = QueueManager::get();

        try {
            switch($reminderType) {
            case UserConfirmRegReminderHandler::REGISTER_REMINDER:
                $confirm               = new Confirm_address();
                $confirm->address_type = $object;
                $confirm->find();
                while ($confirm->fetch()) {
                    try {
                        $qm->enqueue($confirm, 'uregrem');
                    } catch (Exception $e) {
                        common_log(LOG_WARNING, $e->getMessage());
                        continue;
                    }
                }
                break;
            case UserInviteReminderHandler::INVITE_REMINDER:
                $invitation = new Invitation();
                $invitation->find();
                while ($invitation->fetch()) {
                    try {
                        $qm->enqueue($invitation, 'uinvrem');
                    } catch (Exception $e) {
                        common_log(LOG_WARNING, $e->getMessage());
                        continue;
                    }
                }
                break;
            default:
                // WTF?
                common_log(
                    LOG_ERR,
                    "Received unknown confirmation address type",
                    __FILE__
                );
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            return false;
        }

        return true;
    }
}

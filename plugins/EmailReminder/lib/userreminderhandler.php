<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * Base handler for email reminders
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
 * Handler for email reminder queue items
 *
 * @category  Email
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class UserReminderHandler extends QueueHandler {
    /**
     * Send the next email reminder to the confirm address
     *
     * @param Confirm_address $confirm the confirmation email/code
     * @return boolean true on success, false on failure
     */
    function handle($confirm) {
        return $this->sendNextReminder($confirm);
    }

    /**
     * Send the next reminder if one needs sending.
     * Subclasses must override
     *
     * @param Confirm_address $confirm the confirmation email/code
     */
    function sendNextReminder($confirm)
    {
    }
}

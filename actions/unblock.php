<?php
/**
 * Unblock a user action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Unblock a user action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class UnblockAction extends ProfileFormAction
{
    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        $cur = common_current_user();

        assert(!empty($cur)); // checked by parent

        if (!$cur->hasBlocked($this->profile)) {
            // TRANS: Client error displayed when trying to unblock a non-blocked user.
            $this->clientError(_("You haven't blocked that user."));
        }

        return true;
    }

    /**
     * Unblock a user.
     *
     * @return void
     */
    function handlePost()
    {
        $cur = common_current_user();

        $result = false;

        if (Event::handle('StartUnblockProfile', array($cur, $this->profile))) {
            $result = $cur->unblock($this->profile);
            if ($result) {
                Event::handle('EndUnblockProfile', array($cur, $this->profile));
            }
        }

        if (!$result) {
            // TRANS: Server error displayed when removing a user block.
            $this->serverError(_('Error removing the block.'));
        }
    }
}

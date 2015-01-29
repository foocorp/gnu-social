<?php
/**
 * Logout action.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Logout action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class LogoutAction extends ManagedAction
{
    /**
     * This is read only.
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return false;
    }

    protected function doPreparation()
    {
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to logout even though you are not logged in.
            throw new AlreadyFulfilledException(_('Cannot log you out if you are not logged in.'));
        }
        if (Event::handle('StartLogout', array($this))) {
            $this->logout();
        }
        Event::handle('EndLogout', array($this));

        common_redirect(common_local_url('startpage'));
    }

    // Accessed through the action on events
    public function logout()
    {
        common_set_user(null);
        common_real_login(false); // not logged in
        common_forgetme(); // don't log back in!
    }
}

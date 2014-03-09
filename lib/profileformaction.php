<?php
/**
 * Superclass for actions that operate on a user
 *
 * PHP version 5
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for actions that operate on a user
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ProfileFormAction extends RedirectingAction
{
    var $profile = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->checkSessionToken();

        if (!common_logged_in()) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
                $this->clientError(_('Not logged in.'));
            } else {
                // Redirect to login.
                common_set_returnto($this->selfUrl());
                $user = common_current_user();
                if (Event::handle('RedirectToLogin', array($this, $user))) {
                    common_redirect(common_local_url('login'), 303);
                }
            }
            return false;
        }

        $id = $this->trimmed('profileid');

        if (!$id) {
            // TRANS: Client error displayed when trying to change user options without specifying a user to work on.
            $this->clientError(_('No profile specified.'));
        }

        $this->profile = Profile::getKV('id', $id);

        if (!$this->profile) {
            // TRANS: Client error displayed when trying to change user options without specifying an existing user to work on.
            $this->clientError(_('No profile with that ID.'));
        }

        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
            $this->returnToPrevious();
        }
    }

    /**
     * handle a POST request
     *
     * sub-classes should overload this request
     *
     * @return void
     */
    function handlePost()
    {
        // TRANS: Server error displayed when using an unimplemented method.
        $this->serverError(_("Unimplemented method."));
    }
}

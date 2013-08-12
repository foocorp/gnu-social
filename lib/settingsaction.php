<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for settings actions
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for settings group of actions
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */

class SettingsAction extends Action
{
    /**
     * A message for the user.
     */

    var $msg = null;

    /**
     * Whether the message is a good one or a bad one.
     */

    var $success = false;

    /**
     * Handle input and output a page
     *
     * @param array $args $_REQUEST arguments
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return;
        } else if (!common_is_real_login()) {
            // Cookie theft means that automatic logins can't
            // change important settings or see private info, and
            // _all_ our settings are important
            common_set_returnto($this->selfUrl());
            $user = common_current_user();
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->showForm();
        }
    }

    /**
     * Handle a POST request
     *
     * @return boolean success flag
     */

    function handlePost()
    {
        return false;
    }

    /**
     * show the settings form
     *
     * @param string $msg     an extra message for the user
     * @param string $success good message or bad message?
     *
     * @return void
     */

    function showForm($msg=null, $success=false)
    {
        $this->msg     = $msg;
        $this->success = $success;

        $this->showPage();
    }

    /**
     * show human-readable instructions for the page
     *
     * @return void
     */

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('div', ($this->success) ? 'success' : 'error',
                           $this->msg);
        } else {
            $inst   = $this->getInstructions();
            $output = common_markup_to_html($inst);

            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    /**
     * instructions recipe for sub-classes
     *
     * Subclasses should override this to return readable instructions. They'll
     * be processed by common_markup_to_html().
     *
     * @return string instructions text
     */

    function getInstructions()
    {
        return '';
    }

    /**
     * Show the local navigation menu
     *
     * This is the same for all settings, so we show it here.
     *
     * @return void
     */

    function showLocalNav()
    {
        $menu = new SettingsNav($this);
        $menu->show();
    }

    /**
     * Show notice form.
     *
     * @return nothing
     */

    function showNoticeForm()
    {
        return;
    }

    function showProfileBlock()
    {
    }
}

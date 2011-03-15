<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Menu for login group of actions
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
 * @category  Menu
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Menu for login group of actions
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */
class LoginGroupNav extends Menu
{
    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $action_name = $this->action->trimmed('action');

        $this->action->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartLoginGroupNav', array($this->action))) {

            $this->action->menuItem(common_local_url('login'),
                                    // TRANS: Menu item for logging in to the StatusNet site.
                                    _m('MENU','Login'),
                                    // TRANS: Title for menu item for logging in to the StatusNet site.
                                    _('Login with a username and password'),
                                    $action_name === 'login');

            if (!common_logged_in() &&
                !(common_config('site','closed') || common_config('site','inviteonly'))) {
                $this->action->menuItem(common_local_url('register'),
                                        // TRANS: Menu item for registering with the StatusNet site.
                                        _m('MENU','Register'),
                                        // TRANS: Title for menu item for registering with the StatusNet site.
                                        _('Sign up for a new account'),
                                        $action_name === 'register');
            }

            Event::handle('EndLoginGroupNav', array($this->action));
        }

        $this->action->elementEnd('ul');
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Primary nav, show on all pages
 *
 * PHP version 5
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
 * @category  Menu
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Primary, top-level menu
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class PrimaryNav extends Menu
{
    function show()
    {
        $user = common_current_user();
        $this->action->elementStart('ul', array('class' => 'nav'));
        if (Event::handle('StartPrimaryNav', array($this->action))) {
            if (!empty($user)) {
                $this->action->menuItem(common_local_url('profilesettings'),
                                // TRANS: Menu item in primary navigation panel.
                                _m('MENU','Settings'),
                                // TRANS: Menu item title in primary navigation panel.
                                _('Change your personal settings.'),
                                false,
                                'nav_account');
                if ($user->hasRight(Right::CONFIGURESITE)) {
                    $this->action->menuItem(common_local_url('siteadminpanel'),
                                    // TRANS: Menu item in primary navigation panel.
                                    _m('MENU','Admin'),
                                    // TRANS: Menu item title in primary navigation panel.
                                    _('Site configuration.'),
                                    false,
                                    'nav_admin');
                }
                $this->action->menuItem(common_local_url('logout'),
                                // TRANS: Menu item in primary navigation panel.
                                _m('MENU','Logout'),
                                // TRANS: Menu item title in primary navigation panel.
                                _('Logout from the site.'),
                                false,
                                'nav_logout');
            } else {
                $this->action->menuItem(common_local_url('login'),
                                // TRANS: Menu item in primary navigation panel.
                                _m('MENU','Login'),
                                // TRANS: Menu item title in primary navigation panel.
                                _('Login to the site.'),
                                false,
                                'nav_login');
            }

            Event::handle('EndPrimaryNav', array($this->action));
        }

        $this->action->elementEnd('ul');
    }
}

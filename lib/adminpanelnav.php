<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Menu for admin panels
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
 * Menu for admin panels
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link     http://status.net/
 */
class AdminPanelNav extends Menu
{
    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $action_name = $this->action->trimmed('action');
        $user = common_current_user();
        $nickname = $user->nickname;
        $name = $user->getProfile()->getBestName();

        // Stub section w/ home link
        $this->action->elementStart('ul');
        $this->action->elementStart('li');
        // TRANS: Header in administrator navigation panel.
        $this->action->element('h3', null, _m('HEADER','Home'));
        $this->action->elementStart('ul', 'nav');
        $this->out->menuItem(common_local_url('all', array('nickname' =>
                                                           $nickname)),
                             // TRANS: Menu item in administrator navigation panel.
                             _m('MENU','Home'),
                             // TRANS: Menu item title in administrator navigation panel.
                             // TRANS: %s is a username.
                             sprintf(_('%s and friends'), $name),
                             $this->action == 'all', 'nav_timeline_personal');

        $this->action->elementEnd('ul');
        $this->action->elementEnd('li');
        $this->action->elementEnd('ul');

        $this->action->elementStart('ul');
        $this->action->elementStart('li');
        // TRANS: Header in administrator navigation panel.
        $this->action->element('h3', null, _m('HEADER','Admin'));
        $this->action->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartAdminPanelNav', array($this))) {

            if (AdminPanelAction::canAdmin('site')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Basic site configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('siteadminpanel'), _m('MENU', 'Site'),
                                     $menu_title, $action_name == 'siteadminpanel', 'nav_site_admin_panel');
            }

            if (AdminPanelAction::canAdmin('user')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('User configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('useradminpanel'), _m('MENU','User'),
                                     $menu_title, $action_name == 'useradminpanel', 'nav_user_admin_panel');
            }

            if (AdminPanelAction::canAdmin('access')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Access configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('accessadminpanel'), _m('MENU','Access'),
                                     $menu_title, $action_name == 'accessadminpanel', 'nav_access_admin_panel');
            }

            if (AdminPanelAction::canAdmin('paths')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Paths configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('pathsadminpanel'), _m('MENU','Paths'),
                                    $menu_title, $action_name == 'pathsadminpanel', 'nav_paths_admin_panel');
            }

            if (AdminPanelAction::canAdmin('sessions')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Sessions configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('sessionsadminpanel'), _m('MENU','Sessions'),
                                     $menu_title, $action_name == 'sessionsadminpanel', 'nav_sessions_admin_panel');
            }

            if (AdminPanelAction::canAdmin('sitenotice')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Edit site notice');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('sitenoticeadminpanel'), _m('MENU','Site notice'),
                                     $menu_title, $action_name == 'sitenoticeadminpanel', 'nav_sitenotice_admin_panel');
            }

            if (AdminPanelAction::canAdmin('license')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Set site license');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('licenseadminpanel'), _m('MENU','License'),
                                     $menu_title, $action_name == 'licenseadminpanel', 'nav_license_admin_panel');
            }

            if (AdminPanelAction::canAdmin('plugins')) {
                // TRANS: Menu item title in administrator navigation panel.
                $menu_title = _('Plugins configuration');
                // TRANS: Menu item in administrator navigation panel.
                $this->out->menuItem(common_local_url('pluginsadminpanel'), _m('MENU','Plugins'),
                                     $menu_title, $action_name == 'pluginsadminpanel', 'nav_plugin_admin_panel');
            }

            Event::handle('EndAdminPanelNav', array($this));
        }
        $this->action->elementEnd('ul');
        $this->action->elementEnd('li');
        $this->action->elementEnd('ul');
    }
}

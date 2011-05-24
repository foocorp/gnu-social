<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Default local nav
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
 * Default menu
 *
 * @category  Menu
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DefaultLocalNav extends Menu
{
    function show()
    {
        $user = common_current_user();

        $this->action->elementStart('ul', array('id' => 'nav_local_default'));

        if (Event::handle('StartDefaultLocalNav', array($this, $user))) {

            if (!empty($user)) {
                $pn = new PersonalGroupNav($this->action);
                // TRANS: Menu item in default local navigation panel.
                $this->submenu(_m('MENU','Home'), $pn);
            }

            $bn = new PublicGroupNav($this->action);
            // TRANS: Menu item in default local navigation panel.
            $this->submenu(_m('MENU','Public'), $bn);

            if (!empty($user)) {
                $sn = new GroupsNav($this->action, $user);
                if ($sn->haveGroups()) {
                    // TRANS: Menu item in default local navigation panel.
                    $this->submenu(_m('MENU', 'Groups'), $sn);
                }
            }

            if (!empty($user)) {
                $sn = new ListsNav($this->action, $user->getProfile());
                if ($sn->hasLists()) {
                    // TRANS: Menu item in default local navigation panel.
                    $this->submenu(_m('MENU', 'Lists'), $sn);
                }
            }

            Event::handle('EndDefaultLocalNav', array($this, $user));
        }

        $this->action->elementEnd('ul');
    }
}

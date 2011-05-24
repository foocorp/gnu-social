<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Tabset for a particular list
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
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Tabset for a group
 *
 * Shows a group of tabs for a particular user group
 *
 * @category Output
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */
class PeopletagNav extends Menu
{
    var $group = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */
    function __construct($action=null, $profile=null)
    {
        parent::__construct($action);
        $this->profile = $profile;
    }

    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $action_name = $this->action->trimmed('action');
        $nickname = $this->profile->nickname;

        $this->out->elementStart('ul', array('class' => 'nav'));
        if (Event::handle('StartPeopletagGroupNav', array($this))) {
            $this->out->menuItem(common_local_url('peopletagsubscriptions', array('nickname' =>
                                                                     $nickname)),
                                 // TRANS: Menu item in the group navigation page.
                                 _m('MENU','List Subscriptions'),
                                 // TRANS: Tooltip for menu item in the group navigation page.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_m('TOOLTIP','Lists subscribed to by %s.'), $nickname),
                                 $action_name == 'peopletagsubscriptions',
                                 'nav_list_group');
            $this->out->menuItem(common_local_url('peopletagsforuser', array('nickname' =>
                                                                        $nickname)),
                                 // TRANS: Menu item in the group navigation page.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_m('MENU','Lists with %s'), $nickname),
                                 // TRANS: Tooltip for menu item in the group navigation page.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_m('TOOLTIP','Lists with %s.'), $nickname),
                                 $action_name == 'peopletagsforuser',
                                 'nav_lists_with');
            $this->out->menuItem(common_local_url('peopletagsbyuser', array('nickname' =>
                                                                        $nickname)),
                                 // TRANS: Menu item in the group navigation page.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_m('MENU','Lists by %s'), $nickname),
                                 // TRANS: Tooltip for menu item in the group navigation page.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_m('TOOLTIP','Lists by %s.'), $nickname),
                                 $action_name == 'peopletagsbyuser',
                                 'nav_lists_by');
            Event::handle('EndGroupGroupNav', array($this));
        }
        $this->out->elementEnd('ul');
    }
}

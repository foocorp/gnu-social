<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Tabset for a particular group
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
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
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */

class GroupNav extends Widget
{
    var $action = null;
    var $group = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */

    function __construct($action=null, $group=null)
    {
        parent::__construct($action);
        $this->action = $action;
        $this->group = $group;
    }

    /**
     * Show the menu
     *
     * @return void
     */

    function show()
    {
        $action_name = $this->action->trimmed('action');
        $nickname = $this->group->nickname;

        $this->out->elementStart('ul', array('class' => 'nav'));
        if (Event::handle('StartGroupGroupNav', array($this))) {
            $this->out->menuItem(common_local_url('showgroup', array('nickname' =>
                                                                     $nickname)),
                                 // TRANS: Menu item in the group navigation page.
                                 _m('MENU','Group'),
                                 // TRANS: Tooltip for menu item in the group navigation page.
                                 // TRANS: %s is the nickname of the group.
                                 sprintf(_m('TOOLTIP','%s group'), $nickname),
                                 $action_name == 'showgroup',
                                 'nav_group_group');
            $this->out->menuItem(common_local_url('groupmembers', array('nickname' =>
                                                                        $nickname)),
                                 // TRANS: Menu item in the group navigation page.
                                 _m('MENU','Members'),
                                 // TRANS: Tooltip for menu item in the group navigation page.
                                 // TRANS: %s is the nickname of the group.
                                 sprintf(_m('TOOLTIP','%s group members'), $nickname),
                                 $action_name == 'groupmembers',
                                 'nav_group_members');

            $cur = common_current_user();

            if ($cur && $cur->isAdmin($this->group)) {
                $this->out->menuItem(common_local_url('blockedfromgroup', array('nickname' =>
                                                                                $nickname)),
                                     // TRANS: Menu item in the group navigation page. Only shown for group administrators.
                                     _m('MENU','Blocked'),
                                     // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                     // TRANS: %s is the nickname of the group.
                                     sprintf(_m('TOOLTIP','%s blocked users'), $nickname),
                                     $action_name == 'blockedfromgroup',
                                     'nav_group_blocked');
                $this->out->menuItem(common_local_url('editgroup', array('nickname' =>
                                                                         $nickname)),
                                     // TRANS: Menu item in the group navigation page. Only shown for group administrators.
                                     _m('MENU','Admin'),
                                     // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                     // TRANS: %s is the nickname of the group.
                                     sprintf(_m('TOOLTIP','Edit %s group properties'), $nickname),
                                     $action_name == 'editgroup',
                                     'nav_group_admin');
                $this->out->menuItem(common_local_url('grouplogo', array('nickname' =>
                                                                         $nickname)),
                                     // TRANS: Menu item in the group navigation page. Only shown for group administrators.
                                     _m('MENU','Logo'),
                                     // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                     // TRANS: %s is the nickname of the group.
                                     sprintf(_m('TOOLTIP','Add or edit %s logo'), $nickname),
                                     $action_name == 'grouplogo',
                                     'nav_group_logo');
                $this->out->menuItem(common_local_url('groupdesignsettings', array('nickname' =>
                                                                      $nickname)),
                                     // TRANS: Menu item in the group navigation page. Only shown for group administrators.
                                     _m('MENU','Design'),
                                     // TRANS: Tooltip for menu item in the group navigation page. Only shown for group administrators.
                                     // TRANS: %s is the nickname of the group.
                                     sprintf(_m('TOOLTIP','Add or edit %s design'), $nickname),
                                     $action_name == 'groupdesignsettings',
                                     'nav_group_design');
            }
            Event::handle('EndGroupGroupNav', array($this));
        }
        $this->out->elementEnd('ul');
    }
}

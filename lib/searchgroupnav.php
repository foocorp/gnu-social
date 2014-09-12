<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Menu for search group of actions
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
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Menu for public group of actions
 *
 * @category Menu
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */
class SearchGroupNav extends Menu
{
    var $q = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */
    function __construct(Action $action=null, $q = null)
    {
        parent::__construct($action);
        $this->q = $q;
    }

    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $action_name = $this->action->trimmed('action');
        $this->action->elementStart('ul', array('class' => 'nav'));
        $args = array();
        if ($this->q) {
            $args['q'] = $this->q;
        }
        // TRANS: Menu item in search group navigation panel.
        $this->out->menuItem(common_local_url('peoplesearch', $args), _m('MENU','People'),
            // TRANS: Menu item title in search group navigation panel.
            _('Find people on this site'), $action_name == 'peoplesearch', 'nav_search_people');
        // TRANS: Menu item in search group navigation panel.
        $this->out->menuItem(common_local_url('noticesearch', $args), _m('MENU','Notices'),
            // TRANS: Menu item title in search group navigation panel.
            _('Find content of notices'), $action_name == 'noticesearch', 'nav_search_notice');
        // TRANS: Menu item in search group navigation panel.
        $this->out->menuItem(common_local_url('groupsearch', $args), _m('MENU','Groups'),
            // TRANS: Menu item title in search group navigation panel.
            _('Find groups on this site'), $action_name == 'groupsearch', 'nav_search_group');
        $this->action->elementEnd('ul');
    }
}

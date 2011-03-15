<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Menu for public group of actions
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
 * Menu for public group of actions
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */

class PublicGroupNav extends Menu
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

        if (Event::handle('StartPublicGroupNav', array($this))) {
            $this->out->menuItem(common_local_url('public'), _('Public'),
                _('Public timeline'), $action_name == 'public', 'nav_timeline_public');

            $this->out->menuItem(common_local_url('groups'), _('Groups'),
                _('User groups'), $action_name == 'groups', 'nav_groups');

            $this->out->menuItem(common_local_url('publictagcloud'), _('Recent tags'),
                _('Recent tags'), $action_name == 'publictagcloud', 'nav_recent-tags');

            if (count(common_config('nickname', 'featured')) > 0) {
                $this->out->menuItem(common_local_url('featured'), _('Featured'),
                    _('Featured users'), $action_name == 'featured', 'nav_featured');
            }

            $this->out->menuItem(common_local_url('favorited'), _('Popular'),
                _("Popular notices"), $action_name == 'favorited', 'nav_timeline_favorited');

            Event::handle('EndPublicGroupNav', array($this));
        }
        $this->action->elementEnd('ul');
    }
}

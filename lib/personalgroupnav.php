<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Menu for personal group of actions
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Menu for personal group of actions
 *
 * @category Menu
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PersonalGroupNav extends Menu
{
    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $user         = common_current_user();

        if (empty($user)) {
            throw new ServerException('Do not show personal group nav with no current user.');
        }

        $user_profile = $user->getProfile();
        $nickname     = $user->nickname;
        $name         = $user_profile->getBestName();

        $action = $this->actionName;
        $mine = ($this->action->arg('nickname') == $nickname); // @fixme kinda vague

        $this->out->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartPersonalGroupNav', array($this))) {
            $this->out->menuItem(common_local_url('all', array('nickname' =>
                                                               $nickname)),
                                 _('Home'),
                                 sprintf(_('%s and friends'), $name),
                                 $mine && $action =='all', 'nav_timeline_personal');
            $this->out->menuItem(common_local_url('showstream', array('nickname' =>
                                                                      $nickname)),
                                 _('Profile'),
                                 _('Your profile'),
                                 $mine && $action =='showstream',
                                 'nav_profile');
            $this->out->menuItem(common_local_url('replies', array('nickname' =>
                                                                   $nickname)),
                                 _('Replies'),
                                 sprintf(_('Replies to %s'), $name),
                                 $mine && $action =='replies', 'nav_timeline_replies');
            $this->out->menuItem(common_local_url('showfavorites', array('nickname' =>
                                                                         $nickname)),
                                 _('Favorites'),
                                 sprintf(_('%s\'s favorite notices'), ($user_profile) ? $name : _('User')),
                                 $mine && $action =='showfavorites', 'nav_timeline_favorites');

            $cur = common_current_user();

            if ($cur && $cur->id == $user->id &&
                !common_config('singleuser', 'enabled')) {

                $this->out->menuItem(common_local_url('inbox', array('nickname' =>
                                                                     $nickname)),
                                     _('Messages'),
                                     _('Your incoming messages'),
                                     $mine && $action =='inbox');
            }

            Event::handle('EndPersonalGroupNav', array($this));
        }
        $this->out->elementEnd('ul');
    }
}

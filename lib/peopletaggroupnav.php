<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for all actions (~views)
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
 * Base class for all actions
 *
 * This is the base class for all actions in the package. An action is
 * more or less a "view" in an MVC framework.
 *
 * Actions are responsible for extracting and validating parameters; using
 * model classes to read and write to the database; and doing ouput.
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
class PeopletagGroupNav extends Widget
{
    var $action = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */
    function __construct(Action $action=null)
    {
        parent::__construct($action);
        $this->action = $action;
    }

    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $user = null;

	// FIXME: we should probably pass this in and check when PeopletagGroupNav is actually loaded etc.

        $action = $this->action->trimmed('action');

        if (common_config('singleuser', 'enabled')) {
            $nickname = User::singleUserNickname();
        } else {
            $nickname = $this->action->arg('tagger');
        }

        $tag = $this->action->trimmed('tag');

        if ($nickname) {
            $user = User::getKV('nickname', $nickname);
            $user_profile = $user->getProfile();

            if ($tag) {
                $tag = Profile_list::pkeyGet(array('tagger' => $user->id,
                                                   'tag'    => $tag));
            } else {
                $tag = false;
            }

        } else {
            $user_profile = false;
        }

        $this->out->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartPeopletagGroupNav', array($this))) {
            // People tag timeline
            $this->out->menuItem(common_local_url('showprofiletag', array('nickname' => $user_profile->nickname,
                                                                          'tag'    => $tag->tag)),
                             // TRANS: Menu item in list navigation panel.
                             _m('MENU','List'),
                             // TRANS: Menu item title in list navigation panel.
                             // TRANS: %1$s is a list, %2$s is a nickname.
                             sprintf(_('%1$s list by %2$s.'), $tag->tag,
                                (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                             $action == 'showprofiletag', 'nav_timeline_peopletag');

            // Tagged
            $this->out->menuItem(common_local_url('peopletagged', array('tagger' => $user->nickname,
                                                                        'tag'    => $tag->tag)),
                             // TRANS: Menu item in list navigation panel.
                             _m('MENU','Listed'),
                             // TRANS: Menu item title in list navigation panel.
                             // TRANS: %1$s is a list, %2$s is a nickname.
                             sprintf(_('%1$s list by %2$s.'), $tag->tag,
                                (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                             $action == 'peopletagged', 'nav_peopletag_tagged');

            // Subscribers
            $this->out->menuItem(common_local_url('peopletagsubscribers', array('tagger' => $user->nickname,
                                                                                'tag'    => $tag->tag)),
                             // TRANS: Menu item in list navigation panel.
                             _m('MENU','Subscribers'),
                             // TRANS: Menu item title in list navigation panel.
                             // TRANS: %1$s is a list, %2$s is a nickname.
                             sprintf(_('Subscribers to %1$s list by %2$s.'), $tag->tag,
                                (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                             $action == 'peopletagsubscribers', 'nav_peopletag_subscribers');

            $cur = common_current_user();
            if (!empty($cur) && $user_profile->id == $cur->id) {
                // Edit
                $this->out->menuItem(common_local_url('editpeopletag', array('tagger' => $user->nickname,
                                                                             'tag'    => $tag->tag)),
                                 // TRANS: Menu item in list navigation panel.
                                 _m('MENU','Edit'),
                                 // TRANS: Menu item title in list navigation panel.
                                 // TRANS: %s is a list.
                                 sprintf(_('Edit %s list by you.'), $tag->tag,
                                    (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                                 $action == 'editpeopletag', 'nav_peopletag_edit');
            }

            Event::handle('EndPeopletagGroupNav', array($this));
        }
        $this->out->elementEnd('ul');
    }
}

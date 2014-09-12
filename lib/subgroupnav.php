<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Local navigation for subscriptions group of pages
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
 * @category  Subs
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Local nav menu for subscriptions, subscribers
 *
 * @category Subs
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SubGroupNav extends Menu
{
    var $user = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     * @param User   $user   Current user or NULL if "guest"
     */
    function __construct(Action $action=null, User $user=null)
    {
        parent::__construct($action);
        $this->user = $user;
    }

    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $cur = common_current_user();
        $action = $this->action->trimmed('action');

        $this->out->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartSubGroupNav', array($this))) {

            $this->out->menuItem(common_local_url('showstream', array('nickname' =>
                                                                      $this->user->nickname)),
                                 // TRANS: Menu item in local navigation menu.
                                 _m('MENU','Profile'),
                                 (empty($profile)) ? $this->user->nickname : $profile->getBestName(),
                                 $action == 'showstream',
                                 'nav_profile');
            $this->out->menuItem(common_local_url('subscriptions',
                                                  array('nickname' =>
                                                        $this->user->nickname)),
                                 // TRANS: Menu item in local navigation menu.
                                 _m('MENU','Subscriptions'),
                                 // TRANS: Menu item title in local navigation menu.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_('People %s subscribes to.'),
                                         $this->user->nickname),
                                 $action == 'subscriptions',
                                 'nav_subscriptions');
            $this->out->menuItem(common_local_url('subscribers',
                                                  array('nickname' =>
                                                        $this->user->nickname)),
                                 // TRANS: Menu item in local navigation menu.
                                 _m('MENU','Subscribers'),
                                 // TRANS: Menu item title in local navigation menu.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_('People subscribed to %s.'),
                                         $this->user->nickname),
                                 $action == 'subscribers',
                                 'nav_subscribers');
            if ($cur && $cur->id == $this->user->id) {
                // Possibly site admins should be able to get in here too
                $pending = $this->countPendingSubs();
                if ($pending || $cur->subscribe_policy == User::SUBSCRIBE_POLICY_MODERATE) {
                    $this->out->menuItem(common_local_url('subqueue',
                                                          array('nickname' =>
                                                                $this->user->nickname)),
                                         // TRANS: Menu item in local navigation menu.
                                         // TRANS: %d is the number of pending subscription requests.
                                         sprintf(_m('MENU','Pending (%d)'), $pending),
                                         // TRANS: Menu item title in local navigation menu.
                                         sprintf(_('Approve pending subscription requests.'),
                                                 $this->user->nickname),
                                         $action == 'subqueueaction',
                                         'nav_subscribers');
                }
            }
            $this->out->menuItem(common_local_url('usergroups',
                                                  array('nickname' =>
                                                        $this->user->nickname)),
                                 // TRANS: Menu item in local navigation menu.
                                 _m('MENU','Groups'),
                                 // TRANS: Menu item title in local navigation menu.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_('Groups %s is a member of.'),
                                         $this->user->nickname),
                                 $action == 'usergroups',
                                 'nav_usergroups');
            $this->out->menuItem(common_local_url('peopletagsubscriptions',
                                                  array('nickname' =>
                                                        $this->user->nickname)),
                                 // TRANS: Menu item title in local navigation menu.
                                 _m('MENU','Lists'),
                                 // TRANS: Menu item title in local navigation menu.
                                 // TRANS: %s is a user nickname.
                                 sprintf(_('List subscriptions by %s.'),
                                         $this->user->nickname),
                                 in_array($action, array('peopletagsbyuser', 'peopletagsubscriptions', 'peopletagsforuser')),
                                 'nav_timeline_peopletags');

            if (common_config('invite', 'enabled') && !is_null($cur) && $this->user->id === $cur->id) {
                $this->out->menuItem(common_local_url('invite'),
                                     // TRANS: Menu item in local navigation menu.
                                     _m('MENU','Invite'),
                                     // TRANS: Menu item title in local navigation menu.
                                     // TRANS: %s is the StatusNet sitename.
                                     sprintf(_('Invite friends and colleagues to join you on %s.'),
                                             common_config('site', 'name')),
                                     $action == 'invite',
                                     'nav_invite');
            }

            Event::handle('EndSubGroupNav', array($this));
        }

        $this->out->elementEnd('ul');
    }

    function countPendingSubs()
    {
        $req = new Subscription_queue();
        $req->subscribed = $this->user->id;
        return $req->count();
    }
}

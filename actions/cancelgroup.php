<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Leave a group
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Leave a group
 *
 * This is the action for leaving a group. It works more or less like the subscribe action
 * for users.
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class CancelgroupAction extends Action
{
    var $group = null;

    /**
     * Prepare to run
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed when trying to leave a group while not logged in.
            $this->clientError(_('You must be logged in to leave a group.'));
            return false;
        }

        $nickname_arg = $this->trimmed('nickname');
        $id = intval($this->arg('id'));
        if ($id) {
            $this->group = User_group::getKV('id', $id);
        } else if ($nickname_arg) {
            $nickname = common_canonical_nickname($nickname_arg);

            // Permanent redirect on non-canonical nickname
            if ($nickname_arg != $nickname) {
                $args = array('nickname' => $nickname);
                common_redirect(common_local_url('leavegroup', $args), 301);
                return false;
            }

            $local = Local_group::getKV('nickname', $nickname);

            if (!$local) {
                // TRANS: Client error displayed when trying to leave a non-local group.
                $this->clientError(_('No such group.'), 404);
                return false;
            }

            $this->group = User_group::getKV('id', $local->group_id);
        } else {
            // TRANS: Client error displayed when trying to leave a group without providing a group name or group ID.
            $this->clientError(_('No nickname or ID.'), 404);
            return false;
        }

        if (!$this->group) {
            // TRANS: Client error displayed when trying to leave a non-existing group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $cur = common_current_user();
        if (empty($cur)) {
            // TRANS: Client error displayed when trying to leave a group while not logged in.
            $this->clientError(_('Must be logged in.'), 403);
            return false;
        }
        if ($this->arg('profile_id')) {
            if ($cur->isAdmin($this->group)) {
                $this->profile = Profile::getKV('id', $this->arg('profile_id'));
            } else {
                // TRANS: Client error displayed when trying to approve or cancel a group join request without
                // TRANS: being a group administrator.
                $this->clientError(_('Only group admin can approve or cancel join requests.'), 403);
                return false;
            }
        } else {
            $this->profile = $cur->getProfile();
        }

        $this->request = Group_join_queue::pkeyGet(array('profile_id' => $this->profile->id,
                                                         'group_id' => $this->group->id));

        if (empty($this->request)) {
            // TRANS: Client error displayed when trying to approve a non-existing group join request.
            // TRANS: %s is a user nickname.
            $this->clientError(sprintf(_('%s is not in the moderation queue for this group.'), $this->profile->nickname), 403);
        }
        return true;
    }

    /**
     * Handle the request
     *
     * On POST, add the current user to the group
     *
     * @param array $args unused
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        try {
            $this->request->abort();
        } catch (Exception $e) {
            common_log(LOG_ERR, "Exception canceling group sub: " . $e->getMessage());
            // TRANS: Server error displayed when cancelling a queued group join request fails.
            // TRANS: %1$s is the leaving user's nickname, $2$s is the group nickname for which the leave failed.
            $this->serverError(sprintf(_('Could not cancel request for user %1$s to join group %2$s.'),
                                       $this->profile->nickname, $this->group->nickname));
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title for leave group page after leaving.
            // TRANS: %s$s is the leaving user's name, %2$s is the group name.
            $this->element('title', null, sprintf(_m('TITLE','%1$s left group %2$s'),
                                                  $this->profile->nickname,
                                                  $this->group->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            $jf = new JoinForm($this, $this->group);
            $jf->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect(common_local_url('groupmembers', array('nickname' =>
                                                                   $this->group->nickname)),
                            303);
        }
    }
}

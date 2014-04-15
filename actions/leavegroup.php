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

if (!defined('GNUSOCIAL')) { exit(1); }

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
class LeavegroupAction extends Action
{
    var $group = null;

    /**
     * Prepare to run
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed when trying to leave a group while not logged in.
            $this->clientError(_('You must be logged in to leave a group.'));
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
            }

            $local = Local_group::getKV('nickname', $nickname);

            if (!$local) {
                // TRANS: Client error displayed when trying to leave a non-local group.
                $this->clientError(_('No such group.'), 404);
            }

            $this->group = User_group::getKV('id', $local->group_id);
        } else {
            // TRANS: Client error displayed when trying to leave a group without providing a group name or group ID.
            $this->clientError(_('No nickname or ID.'), 404);
        }

        if (!$this->group) {
            // TRANS: Client error displayed when trying to leave a non-existing group.
            $this->clientError(_('No such group.'), 404);
        }

        if (!$this->scoped->isMember($this->group)) {
            // TRANS: Client error displayed when trying to join a group while already a member.
            $this->clientError(_('You are not a member of that group.'), 403);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On POST, add the current user to the group
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        try {
            $this->scoped->leaveGroup($this->group);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Error when {$this->scoped->nickname} tried to leave {$this->group->nickname}: " . $e->getMessage());
            // TRANS: Server error displayed when leaving a group failed in the database.
            // TRANS: %1$s is the leaving user's nickname, $2$s is the group nickname for which the leave failed.
            $this->serverError(sprintf(_('Could not remove user %1$s from group %2$s.'),
                                       $this->scoped->nickname, $this->group->nickname));
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title for leave group page after leaving.
            $this->element('title', null, sprintf(_m('TITLE','%1$s left group %2$s'),
                                                  $this->scoped->nickname,
                                                  $this->group->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            $jf = new JoinForm($this, $this->group);
            $jf->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect(common_local_url('groupmembers', array('nickname' => $this->group->nickname)), 303);
        }
    }
}

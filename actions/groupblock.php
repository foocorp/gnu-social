<?php
/**
 * Block a user from a group action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Block a user from a group
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class GroupblockAction extends RedirectingAction
{
    var $profile = null;
    var $group = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return false;
        }
        $token = $this->trimmed('token');
        if (empty($token) || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        $id = $this->trimmed('blockto');
        if (empty($id)) {
            // TRANS: Client error displayed trying to block a user from a group while not specifying a to be blocked user profile.
            $this->clientError(_('No profile specified.'));
            return false;
        }
        $this->profile = Profile::getKV('id', $id);
        if (empty($this->profile)) {
            // TRANS: Client error displayed trying to block a user from a group while specifying a non-existing profile.
            $this->clientError(_('No profile with that ID.'));
            return false;
        }
        $group_id = $this->trimmed('blockgroup');
        if (empty($group_id)) {
            // TRANS: Client error displayed trying to block a user from a group while not specifying a group to block a profile from.
            $this->clientError(_('No group specified.'));
            return false;
        }
        $this->group = User_group::getKV('id', $group_id);
        if (empty($this->group)) {
            // TRANS: Client error displayed trying to block a user from a group while specifying a non-existing group.
            $this->clientError(_('No such group.'));
            return false;
        }
        $user = common_current_user();
        if (!$user->isAdmin($this->group)) {
            // TRANS: Client error displayed trying to block a user from a group while not being an admin user.
            $this->clientError(_('Only an admin can block group members.'), 401);
            return false;
        }
        if (Group_block::isBlocked($this->group, $this->profile)) {
            // TRANS: Client error displayed trying to block a user from a group while user is already blocked from the given group.
            $this->clientError(_('User is already blocked from group.'));
            return false;
        }
        // XXX: could have proactive blocks, but we don't have UI for it.
        if (!$this->profile->isMember($this->group)) {
            // TRANS: Client error displayed trying to block a user from a group while user is not a member of given group.
            $this->clientError(_('User is not a member of group.'));
            return false;
        }
        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('no')) {
                $this->returnToPrevious();
            } elseif ($this->arg('yes')) {
                $this->blockProfile();
            } elseif ($this->arg('blockto')) {
                $this->showPage();
            }
        }
    }

    function showContent() {
        $this->areYouSureForm();
    }

    function title() {
        // TRANS: Title for block user from group page.
        return _('Block user from group');
    }

    function showNoticeForm() {
        // nop
    }

    /**
     * Confirm with user.
     *
     * Shows a confirmation form.
     *
     * @return void
     */
    function areYouSureForm()
    {
        $id = $this->profile->id;
        $this->elementStart('form', array('id' => 'block-' . $id,
                                           'method' => 'post',
                                           'class' => 'form_settings form_entity_block',
                                           'action' => common_local_url('groupblock')));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        // TRANS: Fieldset legend for block user from group form.
        $this->element('legend', _('Block user'));
        $this->element('p', null,
                       // TRANS: Explanatory text for block user from group form before setting the block.
                       // TRANS: %1$s is that to be blocked user, %2$s is the group the user will be blocked from.
                       sprintf(_('Are you sure you want to block user "%1$s" from the group "%2$s"? '.
                                 'They will be removed from the group, unable to post, and '.
                                 'unable to subscribe to the group in the future.'),
                               $this->profile->getBestName(),
                               $this->group->getBestName()));
        $this->hidden('blockto-' . $this->profile->id,
                      $this->profile->id,
                      'blockto');
        $this->hidden('blockgroup-' . $this->group->id,
                      $this->group->id,
                      'blockgroup');
        foreach ($this->args as $k => $v) {
            if (substr($k, 0, 9) == 'returnto-') {
                $this->hidden($k, $v);
            }
        }
        $this->submit('form_action-no',
                      // TRANS: Button label on the form to block a user from a group.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when blocking a user from a group.
                      _('Do not block this user from this group.'));
        $this->submit('form_action-yes',
                      // TRANS: Button label on the form to block a user from a group.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'yes',
                      // TRANS: Submit button title for 'Yes' when blocking a user from a group.
                      _('Block this user from this group.'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Actually block a user.
     *
     * @return void
     */
    function blockProfile()
    {
        $block = Group_block::blockProfile($this->group, $this->profile,
                                           common_current_user());

        if (empty($block)) {
            // TRANS: Server error displayed when trying to block a user from a group fails because of an application error.
            $this->serverError(_("Database error blocking user from group."));
            return false;
        }

        $this->returnToPrevious();
    }

    /**
     * If we reached this form without returnto arguments, default to
     * the top of the group's member list.
     *
     * @return string URL
     */
    function defaultReturnTo()
    {
        return common_local_url('groupmembers',
                                array('nickname' => $this->group->nickname));
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('form_action-yes');
    }
}

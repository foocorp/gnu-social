<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action class to delete a user
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Delete a user
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class DeleteuserAction extends ProfileFormAction
{
    var $user = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        $cur = common_current_user();

        assert(!empty($cur)); // checked by parent

        if (!$cur->hasRight(Right::DELETEUSER)) {
            // TRANS: Client error displayed when trying to delete a user without having the right to delete users.
            $this->clientError(_('You cannot delete users.'));
            return false;
        }

        $this->user = User::getKV('id', $this->profile->id);

        if (empty($this->user)) {
            // TRANS: Client error displayed when trying to delete a non-local user.
            $this->clientError(_('You can only delete local users.'));
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
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('no')) {
                $this->returnToPrevious();
            } elseif ($this->arg('yes')) {
                $this->handlePost();
                $this->returnToPrevious();
            } else {
                $this->showPage();
            }
        }
    }

    function showContent() {
        $this->areYouSureForm();
    }

    function title() {
        // TRANS: Title of delete user page.
        return _m('TITLE','Delete user');
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
        $this->elementStart('form', array('id' => 'deleteuser-' . $id,
                                           'method' => 'post',
                                           'class' => 'form_settings form_entity_block',
                                           'action' => common_local_url('deleteuser')));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        // TRANS: Fieldset legend on delete user page.
        $this->element('legend', _('Delete user'));
        if (Event::handle('StartDeleteUserForm', array($this, $this->user))) {
            $this->element('p', null,
                           // TRANS: Information text to request if a user is certain that the described action has to be performed.
                           _('Are you sure you want to delete this user? '.
                             'This will clear all data about the user from the '.
                             'database, without a backup.'));
            $this->element('input', array('id' => 'deleteuserto-' . $id,
                                          'name' => 'profileid',
                                          'type' => 'hidden',
                                          'value' => $id));
            foreach ($this->args as $k => $v) {
                if (substr($k, 0, 9) == 'returnto-') {
                    $this->hidden($k, $v);
                }
            }
            Event::handle('EndDeleteUserForm', array($this, $this->user));
        }
        $this->submit('form_action-no',
                      // TRANS: Button label on the delete user form.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when deleting a user.
                      _('Do not delete this user.'));
        $this->submit('form_action-yes',
                      // TRANS: Button label on the delete user form.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'yes',
                      // TRANS: Submit button title for 'Yes' when deleting a user.
                      _('Delete this user.'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Actually delete a user.
     *
     * @return void
     */
    function handlePost()
    {
        if (Event::handle('StartDeleteUser', array($this, $this->user))) {
            // Mark the account as deleted and shove low-level deletion tasks
            // to background queues. Removing a lot of posts can take a while...
            if (!$this->user->hasRole(Profile_role::DELETED)) {
                $this->user->grantRole(Profile_role::DELETED);
            }

            $qm = QueueManager::get();
            $qm->enqueue($this->user, 'deluser');

            Event::handle('EndDeleteUser', array($this, $this->user));
        }
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Delete your own account
 *
 * PHP version 5
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
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Action to delete your own account
 *
 * Note that this is distinct from DeleteuserAction, which see. I thought
 * that making that action do both things (delete another user and delete the
 * current user) would open a lot of holes. I'm open to refactoring, however.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DeleteaccountAction extends Action
{
    private $_complete = false;
    private $_error    = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $cur = common_current_user();

        if (empty($cur)) {
            // TRANS: Client exception displayed trying to delete a user account while not logged in.
            throw new ClientException(_("Only logged-in users ".
                                        "can delete their account."), 403);
        }

        if (!$cur->hasRight(Right::DELETEACCOUNT)) {
            // TRANS: Client exception displayed trying to delete a user account without have the rights to do that.
            throw new ClientException(_("You cannot delete your account."), 403);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->deleteAccount();
        } else {
            $this->showPage();
        }
        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return false;
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }

    /**
     * Delete the current user's account
     *
     * Checks for the "I am sure." string to make sure the user really
     * wants to delete their account.
     *
     * Then, marks the account as deleted and begins the deletion process
     * (actually done by a back-end handler).
     *
     * If successful it logs the user out, and shows a brief completion message.
     *
     * @return void
     */
    function deleteAccount()
    {
        $this->checkSessionToken();
        // !!! If this string is changed, it also needs to be changed in DeleteAccountForm::formData()
        // TRANS: Confirmation text for user deletion. The user has to type this exactly the same, including punctuation.
        $iamsure = _('I am sure.');
        if ($this->trimmed('iamsure') != $iamsure ) {
            // TRANS: Notification for user about the text that must be input to be able to delete a user account.
            // TRANS: %s is the text that needs to be input.
            $this->_error = sprintf(_('You must write "%s" exactly in the box.'), $iamsure);
            $this->showPage();
            return;
        }

        $cur = common_current_user();

        // Mark the account as deleted and shove low-level deletion tasks
        // to background queues. Removing a lot of posts can take a while...

        if (!$cur->hasRole(Profile_role::DELETED)) {
            $cur->grantRole(Profile_role::DELETED);
        }

        $qm = QueueManager::get();
        $qm->enqueue($cur, 'deluser');

        // The user is really-truly logged out

        common_set_user(null);
        common_real_login(false); // not logged in
        common_forgetme(); // don't log back in!

        $this->_complete = true;
        $this->showPage();
    }

    /**
     * Shows the page content.
     *
     * If the deletion is complete, just shows a completion message.
     *
     * Otherwise, shows the deletion form.
     *
     * @return void
     *
     */
    function showContent()
    {
        if ($this->_complete) {
            $this->element('p', 'confirmation',
                           // TRANS: Confirmation that a user account has been deleted.
                           _('Account deleted.'));
            return;
        }

        if (!empty($this->_error)) {
            $this->element('p', 'error', $this->_error);
            $this->_error = null;
        }

        $form = new DeleteAccountForm($this);
        $form->show();
    }

    /**
     * Show the title of the page
     *
     * @return string title
     */

    function title()
    {
        // TRANS: Page title for page on which a user account can be deleted.
        return _('Delete account');
    }
}

/**
 * Form for deleting your account
 *
 * Note that this mostly is here to keep you from accidentally deleting your
 * account.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DeleteAccountForm extends Form
{
    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_profile_delete';
    }

    /**
     * URL the form posts to
     *
     * @return string the form's action URL
     */
    function action()
    {
        return common_local_url('deleteaccount');
    }

    /**
     * Output form data
     *
     * Instructions plus an 'i am sure' entry box.
     *
     * @return void
     */
    function formData()
    {
        $cur = common_current_user();

        // TRANS: Form text for user deletion form.
        $msg = '<p>' . _('This will <strong>permanently delete</strong> '.
                 'your account data from this server.') . '</p>';

        if ($cur->hasRight(Right::BACKUPACCOUNT)) {
            // TRANS: Additional form text for user deletion form shown if a user has account backup rights.
            // TRANS: %s is a URL to the backup page.
            $msg .= '<p>' . sprintf(_('You are strongly advised to '.
                              '<a href="%s">back up your data</a>'.
                              ' before deletion.'),
                           common_local_url('backupaccount')) . '</p>';
        }

        $this->out->elementStart('p');
        $this->out->raw($msg);
        $this->out->elementEnd('p');

        // !!! If this string is changed, it also needs to be changed in class DeleteaccountAction.
        // TRANS: Confirmation text for user deletion. The user has to type this exactly the same, including punctuation.
        $iamsure = _("I am sure.");
        $this->out->input('iamsure',
                          // TRANS: Field label for delete account confirmation entry.
                          _('Confirm'),
                          null,
                          // TRANS: Input title for the delete account field.
                          // TRANS: %s is the text that needs to be input.
                          sprintf(_('Enter "%s" to confirm that '.
                            'you want to delete your account.'),$iamsure ));
    }

    /**
     * Buttons for the form
     *
     * In this case, a single submit button
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Button text for user account deletion.
                           _m('BUTTON', 'Delete'),
                           'submit',
                           null,
                           // TRANS: Button title for user account deletion.
                           _('Permanently delete your account.'));
    }
}

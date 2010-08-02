<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for Jabber/XMPP integration
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/connectsettingsaction.php';
require_once INSTALLDIR.'/lib/jabber.php';

/**
 * Settings for Jabber/XMPP integration
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class ImsettingsAction extends ConnectSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Title for instance messaging settings.
        return _('IM settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instant messaging settings page instructions.
        // TRANS: [instant messages] is link text, "(%%doc.im%%)" is the link.
        // TRANS: the order and formatting of link text and link should remain unchanged.
        return _('You can send and receive notices through '.
                 'Jabber/GTalk [instant messages](%%doc.im%%). '.
                 'Configure your address and settings below.');
    }

    /**
     * Content area of the page
     *
     * We make different sections of the form for the different kinds of
     * functions, and have submit buttons with different names. These
     * are muxed by handlePost() to see what the user really wants to do.
     *
     * @return void
     */

    function showContent()
    {
        if (!common_config('xmpp', 'enabled')) {
            $this->element('div', array('class' => 'error'),
                           // TRANS: Message given in the IM settings if XMPP is not enabled on the site.
                           _('IM is not available.'));
            return;
        }

        $user = common_current_user();
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_im',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('imsettings')));
        $this->elementStart('fieldset', array('id' => 'settings_im_address'));
        // TRANS: Form legend for IM settings form.
        $this->element('legend', null, _('IM address'));
        $this->hidden('token', common_session_token());

        if ($user->jabber) {
            $this->element('p', 'form_confirmed', $user->jabber);
            // TRANS: Form note in IM settings form.
            $this->element('p', 'form_note',
                           _('Current confirmed Jabber/GTalk address.'));
            $this->hidden('jabber', $user->jabber);
            // TRANS: Button label to remove a confirmed IM address.
            $this->submit('remove', _m('BUTTON','Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $this->element('p', 'form_unconfirmed', $confirm->address);
                $this->element('p', 'form_note',
                               // TRANS: Form note in IM settings form.
                               // TRANS: %s is the IM address set for the site.
                               sprintf(_('Awaiting confirmation on this address. '.
                                         'Check your Jabber/GTalk account for a '.
                                         'message with further instructions. '.
                                         '(Did you add %s to your buddy list?)'),
                                       jabber_daemon_address()));
                $this->hidden('jabber', $confirm->address);
                // TRANS: Button label to cancel an IM address confirmation procedure.
                $this->submit('cancel', _m('BUTTON','Cancel'));
            } else {
                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                // TRANS: Field label for IM address input in IM settings form.
                $this->input('jabber', _('IM address'),
                             ($this->arg('jabber')) ? $this->arg('jabber') : null,
                             // TRANS: IM address input field instructions in IM settings form.
                             // TRANS: %s is the IM address set for the site.
                             sprintf(_('Jabber or GTalk address, '.
                                       'like "UserName@example.org". '.
                                       'First, make sure to add %s to your '.
                                       'buddy list in your IM client or on GTalk.'),
                                     jabber_daemon_address()));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                // TRANS: Button label for adding an IM address in IM settings form.
                $this->submit('add', _m('BUTTON','Add'));
            }
        }
        $this->elementEnd('fieldset');
        
        $this->elementStart('fieldset', array('id' => 'settings_im_preferences'));
        // TRANS: Form legend for IM preferences form.
        $this->element('legend', null, _('IM preferences'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('jabbernotify',
                        // TRANS: Checkbox label in IM preferences form.
                        _('Send me notices through Jabber/GTalk.'),
                        $user->jabbernotify);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('updatefrompresence',
                        // TRANS: Checkbox label in IM preferences form.
                        _('Post a notice when my Jabber/GTalk status changes.'),
                        $user->updatefrompresence);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('jabberreplies',
                        // TRANS: Checkbox label in IM preferences form.
                        _('Send me replies through Jabber/GTalk '.
                          'from people I\'m not subscribed to.'),
                        $user->jabberreplies);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('jabbermicroid',
                        // TRANS: Checkbox label in IM preferences form.
                        _('Publish a MicroID for my Jabber/GTalk address.'),
                        $user->jabbermicroid);
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Button label to save IM preferences.
        $this->submit('save', _m('BUTTON','Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Get a confirmation code for this user
     *
     * @return Confirm_address address object for this user
     */

    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'jabber';

        if ($confirm->find(true)) {
            return $confirm;
        } else {
            return null;
        }
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->savePreferences();
        } else if ($this->arg('add')) {
            $this->addAddress();
        } else if ($this->arg('cancel')) {
            $this->cancelConfirmation();
        } else if ($this->arg('remove')) {
            $this->removeAddress();
        } else {
            // TRANS: Message given submitting a form with an unknown action in IM settings.
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Save user's Jabber preferences
     *
     * These are the checkboxes at the bottom of the page. They're used to
     * set different settings
     *
     * @return void
     */

    function savePreferences()
    {
        $jabbernotify       = $this->boolean('jabbernotify');
        $updatefrompresence = $this->boolean('updatefrompresence');
        $jabberreplies      = $this->boolean('jabberreplies');
        $jabbermicroid      = $this->boolean('jabbermicroid');

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->jabbernotify       = $jabbernotify;
        $user->updatefrompresence = $updatefrompresence;
        $user->jabberreplies      = $jabberreplies;
        $user->jabbermicroid      = $jabbermicroid;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error thrown on database error updating IM preferences.
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }

        $user->query('COMMIT');

        // TRANS: Confirmation message for successful IM preferences save.
        $this->showForm(_('Preferences saved.'), true);
    }

    /**
     * Sends a confirmation to the address given
     *
     * Stores a confirmation record and sends out a
     * Jabber message with the confirmation info.
     *
     * @return void
     */

    function addAddress()
    {
        $user = common_current_user();

        $jabber = $this->trimmed('jabber');

        // Some validation

        if (!$jabber) {
            // TRANS: Message given saving IM address without having provided one.
            $this->showForm(_('No Jabber ID.'));
            return;
        }

        $jabber = jabber_normalize_jid($jabber);

        if (!$jabber) {
            // TRANS: Message given saving IM address that cannot be normalised.
            $this->showForm(_('Cannot normalize that Jabber ID'));
            return;
        }
        if (!jabber_valid_base_jid($jabber, common_config('email', 'domain_check'))) {
            // TRANS: Message given saving IM address that not valid.
            $this->showForm(_('Not a valid Jabber ID'));
            return;
        } else if ($user->jabber == $jabber) {
            // TRANS: Message given saving IM address that is already set.
            $this->showForm(_('That is already your Jabber ID.'));
            return;
        } else if ($this->jabberExists($jabber)) {
            // TRANS: Message given saving IM address that is already set for another user.
            $this->showForm(_('Jabber ID already belongs to another user.'));
            return;
        }

        $confirm = new Confirm_address();

        $confirm->address      = $jabber;
        $confirm->address_type = 'jabber';
        $confirm->user_id      = $user->id;
        $confirm->code         = common_confirmation_code(64);
        $confirm->sent         = common_sql_now();
        $confirm->claimed      = common_sql_now();

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            // TRANS: Server error thrown on database error adding IM confirmation code.
            $this->serverError(_('Couldn\'t insert confirmation code.'));
            return;
        }

        jabber_confirm_address($confirm->code,
                               $user->nickname,
                               $jabber);

        // TRANS: Message given saving valid IM address that is to be confirmed.
        // TRANS: %s is the IM address set for the site.
        $msg = sprintf(_('A confirmation code was sent '.
                         'to the IM address you added. '.
                         'You must approve %s for '.
                         'sending messages to you.'),
                       jabber_daemon_address());

        $this->showForm($msg, true);
    }

    /**
     * Cancel a confirmation
     *
     * If a confirmation exists, cancel it.
     *
     * @return void
     */

    function cancelConfirmation()
    {
        $jabber = $this->arg('jabber');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            // TRANS: Message given canceling IM address confirmation that is not pending.
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $jabber) {
            // TRANS: Message given canceling IM address confirmation for the wrong IM address.
            $this->showForm(_('That is the wrong IM address.'));
            return;
        }

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error thrown on database error canceling IM address confirmation.
            $this->serverError(_('Couldn\'t delete IM confirmation.'));
            return;
        }

        // TRANS: Message given after successfully canceling IM address confirmation.
        $this->showForm(_('IM confirmation cancelled.'), true);
    }

    /**
     * Remove an address
     *
     * If the user has a confirmed address, remove it.
     *
     * @return void
     */

    function removeAddress()
    {
        $user = common_current_user();

        $jabber = $this->arg('jabber');

        // Maybe an old tab open...?

        if ($user->jabber != $jabber) {
            // TRANS: Message given trying to remove an IM address that is not
            // TRANS: registered for the active user.
            $this->showForm(_('That is not your Jabber ID.'));
            return;
        }

        $user->query('BEGIN');

        $original = clone($user);

        $user->jabber = null;

        $result = $user->updateKeys($original);

        if (!$result) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error thrown on database error removing a registered IM address.
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }
        $user->query('COMMIT');

        // XXX: unsubscribe to the old address

        // TRANS: Message given after successfully removing a registered IM address.
        $this->showForm(_('The IM address was removed.'), true);
    }

    /**
     * Does this Jabber ID exist?
     *
     * Checks if we already have another user with this address.
     *
     * @param string $jabber Address to check
     *
     * @return boolean whether the Jabber ID exists
     */

    function jabberExists($jabber)
    {
        $user = common_current_user();

        $other = User::staticGet('jabber', $jabber);

        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }
}

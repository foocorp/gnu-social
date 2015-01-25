<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for SMS
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

/**
 * Settings for SMS
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class SmssettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for SMS settings.
        return _('SMS settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // XXX: For consistency of parameters in messages, this should be a
        //      regular parameters, replaced with sprintf().
        // TRANS: SMS settings page instructions.
        // TRANS: %%site.name%% is the name of the site.
        return _('You can receive SMS messages through email from %%site.name%%.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('sms');
    }

    /**
     * Content area of the page
     *
     * Shows a form for adding and removing SMS phone numbers and setting
     * SMS preferences.
     *
     * @return void
     */
    function showContent()
    {
        if (!common_config('sms', 'enabled')) {
            $this->element('div', array('class' => 'error'),
                           // TRANS: Message given in the SMS settings if SMS is not enabled on the site.
                           _('SMS is not available.'));
            return;
        }

        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_sms',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('smssettings')));

        $this->elementStart('fieldset', array('id' => 'settings_sms_address'));
        // TRANS: Form legend for SMS settings form.
        $this->element('legend', null, _('SMS address'));
        $this->hidden('token', common_session_token());

        if ($user->sms) {
            $carrier = $user->getCarrier();
            $this->element('p', 'form_confirmed',
                           $user->sms . ' (' . $carrier->name . ')');
            $this->element('p', 'form_guide',
                           // TRANS: Form guide in SMS settings form.
                           _('Current confirmed SMS-enabled phone number.'));
            $this->hidden('sms', $user->sms);
            $this->hidden('carrier', $user->carrier);
            // TRANS: Button label to remove a confirmed SMS address.
            $this->submit('remove', _m('BUTTON','Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $carrier = Sms_carrier::getKV($confirm->address_extra);
                $this->element('p', 'form_unconfirmed',
                               $confirm->address . ' (' . $carrier->name . ')');
                $this->element('p', 'form_guide',
                               // TRANS: Form guide in IM settings form.
                               _('Awaiting confirmation on this phone number.'));
                $this->hidden('sms', $confirm->address);
                $this->hidden('carrier', $confirm->address_extra);
                // TRANS: Button label to cancel a SMS address confirmation procedure.
                $this->submit('cancel', _m('BUTTON','Cancel'));

                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                // TRANS: Field label for SMS address input in SMS settings form.
                $this->input('code', _('Confirmation code'), null,
                             // TRANS: Form field instructions in SMS settings form.
                             _('Enter the code you received on your phone.'));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                // TRANS: Button label to confirm SMS confirmation code in SMS settings.
                $this->submit('confirm', _m('BUTTON','Confirm'));
            } else {
                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                // TRANS: Field label for SMS phone number input in SMS settings form.
                $this->input('sms', _('SMS phone number'),
                             ($this->arg('sms')) ? $this->arg('sms') : null,
                             // TRANS: SMS phone number input field instructions in SMS settings form.
                             _('Phone number, no punctuation or spaces, '.
                               'with area code.'));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                $this->carrierSelect();
                // TRANS: Button label for adding a SMS phone number in SMS settings form.
                $this->submit('add', _m('BUTTON','Add'));
            }
        }
        $this->elementEnd('fieldset');

        if ($user->sms) {
        $this->elementStart('fieldset', array('id' => 'settings_sms_incoming_email'));
            // XXX: Confused! This is about SMS. Should this message be updated?
            // TRANS: Form legend for incoming SMS settings form.
            $this->element('legend', null, _('Incoming email'));

            if ($user->incomingemail) {
                $this->element('p', 'form_unconfirmed', $user->incomingemail);
                $this->element('p', 'form_note',
                               // XXX: Confused! This is about SMS. Should this message be updated?
                               // TRANS: Form instructions for incoming SMS e-mail address form in SMS settings.
                               _('Send email to this address to post new notices.'));
                // TRANS: Button label for removing a set sender SMS e-mail address to post notices from.
                $this->submit('removeincoming', _m('BUTTON','Remove'));
            }

            $this->element('p', 'form_guide',
                           // XXX: Confused! This is about SMS. Should this message be updated?
                           // TRANS: Instructions for incoming SMS e-mail address input form.
                           _('Make a new email address for posting to; '.
                             'cancels the old one.'));
            // TRANS: Button label for adding an SMS e-mail address to send notices from.
            $this->submit('newincoming', _m('BUTTON','New'));
            $this->elementEnd('fieldset');
        }

        $this->elementStart('fieldset', array('id' => 'settings_sms_preferences'));
        // TRANS: Form legend for SMS preferences form.
        $this->element('legend', null, _('SMS preferences'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('smsnotify',
                        // TRANS: Checkbox label in SMS preferences form.
                        _('Send me notices through SMS; '.
                          'I understand I may incur '.
                          'exorbitant charges from my carrier.'),
                        $user->smsnotify);
        $this->elementEnd('li');
        $this->elementEnd('ul');

        // TRANS: Button label to save SMS preferences.
        $this->submit('save', _m('BUTTON','Save'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Get a pending confirmation, if any, for this user
     *
     * @return void
     *
     * @todo very similar to EmailsettingsAction::getConfirmation(); refactor?
     */
    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'sms';

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
            // TRANS: Client error displayed when the session token does not match or is not given.
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
        } else if ($this->arg('removeincoming')) {
            $this->removeIncoming();
        } else if ($this->arg('newincoming')) {
            $this->newIncoming();
        } else if ($this->arg('confirm')) {
            $this->confirmCode();
        } else {
            // TRANS: Message given submitting a form with an unknown action in SMS settings.
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Handle a request to save preferences
     *
     * Sets the user's SMS preferences in the DB.
     *
     * @return void
     */
    function savePreferences()
    {
        $smsnotify = $this->boolean('smsnotify');

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->smsnotify = $smsnotify;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error thrown on database error updating SMS preferences.
            $this->serverError(_('Could not update user.'));
        }

        $user->query('COMMIT');

        // TRANS: Confirmation message for successful SMS preferences save.
        $this->showForm(_('SMS preferences saved.'), true);
    }

    /**
     * Add a new SMS number for confirmation
     *
     * When the user requests a new SMS number, sends a confirmation
     * message.
     *
     * @return void
     */
    function addAddress()
    {
        $user = common_current_user();

        $sms        = $this->trimmed('sms');
        $carrier_id = $this->trimmed('carrier');

        // Some validation

        if (!$sms) {
            // TRANS: Message given saving SMS phone number without having provided one.
            $this->showForm(_('No phone number.'));
            return;
        }

        if (!$carrier_id) {
            // TRANS: Message given saving SMS phone number without having selected a carrier.
            $this->showForm(_('No carrier selected.'));
            return;
        }

        $sms = common_canonical_sms($sms);

        if ($user->sms == $sms) {
            // TRANS: Message given saving SMS phone number that is already set.
            $this->showForm(_('That is already your phone number.'));
            return;
        } else if ($this->smsExists($sms)) {
            // TRANS: Message given saving SMS phone number that is already set for another user.
            $this->showForm(_('That phone number already belongs to another user.'));
            return;
        }

        $confirm = new Confirm_address();

        $confirm->address       = $sms;
        $confirm->address_extra = $carrier_id;
        $confirm->address_type  = 'sms';
        $confirm->user_id       = $user->id;
        $confirm->code          = common_confirmation_code(40);

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            // TRANS: Server error thrown on database error adding SMS confirmation code.
            $this->serverError(_('Could not insert confirmation code.'));
        }

        $carrier = Sms_carrier::getKV($carrier_id);

        mail_confirm_sms($confirm->code,
                         $user->nickname,
                         $carrier->toEmailAddress($sms));

        // TRANS: Message given saving valid SMS phone number that is to be confirmed.
        $msg = _('A confirmation code was sent to the phone number you added. '.
                 'Check your phone for the code and instructions '.
                 'on how to use it.');

        $this->showForm($msg, true);
    }

    /**
     * Cancel a pending confirmation
     *
     * Cancels the confirmation.
     *
     * @return void
     */
    function cancelConfirmation()
    {
        $sms     = $this->trimmed('sms');
        $carrier = $this->trimmed('carrier');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            // TRANS: Message given canceling SMS phone number confirmation that is not pending.
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $sms) {
            // TRANS: Message given canceling SMS phone number confirmation for the wrong phone number.
            $this->showForm(_('That is the wrong confirmation number.'));
            return;
        }

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error thrown on database error canceling SMS phone number confirmation.
            $this->serverError(_('Could not delete SMS confirmation.'));
        }

        // TRANS: Message given after successfully canceling SMS phone number confirmation.
        $this->showForm(_('SMS confirmation cancelled.'), true);
    }

    /**
     * Remove a phone number from the user's account
     *
     * @return void
     */
    function removeAddress()
    {
        $user = common_current_user();

        $sms     = $this->arg('sms');
        $carrier = $this->arg('carrier');

        // Maybe an old tab open...?

        if ($user->sms != $sms) {
            // TRANS: Message given trying to remove an SMS phone number that is not
            // TRANS: registered for the active user.
            $this->showForm(_('That is not your phone number.'));
            return;
        }

        $user->query('BEGIN');

        $original = clone($user);

        $user->sms      = null;
        $user->carrier  = null;
        $user->smsemail = null;

        $result = $user->updateWithKeys($original);
        if (!$result) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error thrown on database error removing a registered SMS phone number.
            $this->serverError(_('Could not update user.'));
        }
        $user->query('COMMIT');

        // TRANS: Message given after successfully removing a registered SMS phone number.
        $this->showForm(_('The SMS phone number was removed.'), true);
    }

    /**
     * Does this sms number exist in our database?
     *
     * Also checks if it belongs to someone else
     *
     * @param string $sms phone number to check
     *
     * @return boolean does the number exist
     */
    function smsExists($sms)
    {
        $user = common_current_user();

        $other = User::getKV('sms', $sms);

        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }

    /**
     * Show a drop-down box with all the SMS carriers in the DB
     *
     * @return void
     */
    function carrierSelect()
    {
        $carrier = new Sms_carrier();

        $cnt = $carrier->find();

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Label for mobile carrier dropdown menu in SMS settings.
        $this->element('label', array('for' => 'carrier'), _('Mobile carrier'));
        $this->elementStart('select', array('name' => 'carrier',
                                            'id' => 'carrier'));
        $this->element('option', array('value' => 0),
                       // TRANS: Default option for mobile carrier dropdown menu in SMS settings.
                       _('Select a carrier'));
        while ($carrier->fetch()) {
            $this->element('option', array('value' => $carrier->id),
                           $carrier->name);
        }
        $this->elementEnd('select');
        $this->element('p', 'form_guide',
                       // TRANS: Form instructions for mobile carrier dropdown menu in SMS settings.
                       // TRANS: %s is an administrative contact's e-mail address.
                       sprintf(_('Mobile carrier for your phone. '.
                                 'If you know a carrier that accepts ' .
                                 'SMS over email but isn\'t listed here, ' .
                                 'send email to let us know at %s.'),
                               common_config('site', 'email')));
        $this->elementEnd('li');
        $this->elementEnd('ul');
    }

    /**
     * Confirm an SMS confirmation code
     *
     * Redirects to the confirmaddress page for this code
     *
     * @return void
     */
    function confirmCode()
    {
        $code = $this->trimmed('code');

        if (!$code) {
            // TRANS: Message given saving SMS phone number confirmation code without having provided one.
            $this->showForm(_('No code entered.'));
            return;
        }

        common_redirect(common_local_url('confirmaddress',
                                         array('code' => $code)),
                        303);
    }

    /**
     * Handle a request to remove an incoming email address
     *
     * @return void
     */
    function removeIncoming()
    {
        $user = common_current_user();

        if (!$user->incomingemail) {
            // TRANS: Form validation error displayed when trying to remove an incoming e-mail address while no address has been set.
            $this->showForm(_('No incoming email address.'));
            return;
        }

        $orig = clone($user);

        $user->incomingemail = null;

        if (!$user->updateWithKeys($orig)) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when the user could not be updated in SMS settings.
            $this->serverError(_('Could not update user record.'));
        }

        // TRANS: Confirmation text after updating SMS settings.
        $this->showForm(_('Incoming email address removed.'), true);
    }

    /**
     * Generate a new incoming email address
     *
     * @return void
     *
     * @see Emailsettings::newIncoming
     */
    function newIncoming()
    {
        $user = common_current_user();

        $orig = clone($user);

        $user->incomingemail = mail_new_incoming_address();

        if (!$user->updateWithKeys($orig)) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when the user could not be updated in SMS settings.
            $this->serverError(_('Could not update user record.'));
        }

        // TRANS: Confirmation text after updating SMS settings.
        $this->showForm(_('New incoming email address added.'), true);
    }
}

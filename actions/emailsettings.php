<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for email
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}



/**
 * Settings for email
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */

class EmailsettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for e-mail settings.
        return _('Email settings');
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
        // TRANS: E-mail settings page instructions.
        // TRANS: %%site.name%% is the name of the site.
        return _('Manage how you get email from %%site.name%%.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->script('emailsettings.js');
        $this->autofocus('email');
    }

    /**
     * Content area of the page
     *
     * Shows a form for adding and removing email addresses and setting
     * email preferences.
     *
     * @return void
     */
    function showContent()
    {
        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_email',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('emailsettings')));
        $this->elementStart('fieldset');
        $this->elementStart('fieldset', array('id' => 'settings_email_address'));
        // TRANS: Form legend for e-mail settings form.
        $this->element('legend', null, _('Email address'));
        $this->hidden('token', common_session_token());

        if ($user->email) {
            $this->element('p', array('id' => 'form_confirmed'), $user->email);
            // TRANS: Form note in e-mail settings form.
            $this->element('p', array('class' => 'form_note'), _('Current confirmed email address.'));
            $this->hidden('email', $user->email);
            // TRANS: Button label to remove a confirmed e-mail address.
            $this->submit('remove', _m('BUTTON','Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $this->element('p', array('id' => 'form_unconfirmed'), $confirm->address);
                $this->element('p', array('class' => 'form_note'),
                                        // TRANS: Form note in e-mail settings form.
                                        _('Awaiting confirmation on this address. '.
                                        'Check your inbox (and spam box!) for a message '.
                                        'with further instructions.'));
                $this->hidden('email', $confirm->address);
                // TRANS: Button label to cancel an e-mail address confirmation procedure.
                $this->submit('cancel', _m('BUTTON','Cancel'));
            } else {
                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                // TRANS: Field label for e-mail address input in e-mail settings form.
                $this->input('email', _('Email address'),
                             ($this->arg('email')) ? $this->arg('email') : null,
                             // TRANS: Instructions for e-mail address input form. Do not translate
                             // TRANS: "example.org". It is one of the domain names reserved for
                             // TRANS: use in examples by http://www.rfc-editor.org/rfc/rfc2606.txt.
                             // TRANS: Any other domain may be owned by a legitimate person or
                             // TRANS: organization.
                             _('Email address, like "UserName@example.org"'));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                // TRANS: Button label for adding an e-mail address in e-mail settings form.
                $this->submit('add', _m('BUTTON','Add'));
            }
        }
        $this->elementEnd('fieldset');

       if (common_config('emailpost', 'enabled') && $user->email) {
            $this->elementStart('fieldset', array('id' => 'settings_email_incoming'));
            // TRANS: Form legend for incoming e-mail settings form.
            $this->element('legend', null, _('Incoming email'));

            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->checkbox('emailpost',
                    // TRANS: Checkbox label in e-mail preferences form.
                    _('I want to post notices by email.'),
                    $user->emailpost);
            $this->elementEnd('li');
            $this->elementEnd('ul');

            // Our stylesheets make the form_data list items all floats, which
            // creates lots of problems with trying to wrap divs around things.
            // This should force a break before the next section, which needs
            // to be separate so we can disable the things in it when the
            // checkbox is off.
            $this->elementStart('div', array('style' => 'clear: both'));
            $this->elementEnd('div');

            $this->elementStart('div', array('id' => 'emailincoming'));

            if ($user->incomingemail) {
                $this->elementStart('p');
                $this->element('span', 'address', $user->incomingemail);
                // @todo XXX: Looks a little awkward in the UI.
                //      Something like "xxxx@identi.ca  Send email ..". Needs improvement.
                $this->element('span', 'input_instructions',
                               // TRANS: Form instructions for incoming e-mail form in e-mail settings.
                               _('Send email to this address to post new notices.'));
                $this->elementEnd('p');
                // TRANS: Button label for removing a set sender e-mail address to post notices from.
                $this->submit('removeincoming', _m('BUTTON','Remove'));
            }

            $this->elementStart('p');
            if ($user->incomingemail) {
                // TRANS: Instructions for incoming e-mail address input form, when an address has already been assigned.
                $msg = _('Make a new email address for posting to; '.
                         'cancels the old one.');
            } else {
                // TRANS: Instructions for incoming e-mail address input form.
                $msg = _('To send notices via email, we need to create a unique email address for you on this server:');
            }
            $this->element('span', 'input_instructions', $msg);
            $this->elementEnd('p');

            // TRANS: Button label for adding an e-mail address to send notices from.
            $this->submit('newincoming', _m('BUTTON','New'));

            $this->elementEnd('div'); // div#emailincoming

            $this->elementEnd('fieldset');
        }

        $this->elementStart('fieldset', array('id' => 'settings_email_preferences'));
        // TRANS: Form legend for e-mail preferences form.
        $this->element('legend', null, _('Email preferences'));

        $this->elementStart('ul', 'form_data');

        if (Event::handle('StartEmailFormData', array($this, $this->scoped))) {
            $this->elementStart('li');
            $this->checkbox('emailnotifysub',
                            // TRANS: Checkbox label in e-mail preferences form.
                            _('Send me notices of new subscriptions through email.'),
                            $user->emailnotifysub);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('emailnotifymsg',
                            // TRANS: Checkbox label in e-mail preferences form.
                            _('Send me email when someone sends me a private message.'),
                            $user->emailnotifymsg);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('emailnotifyattn',
                            // TRANS: Checkbox label in e-mail preferences form.
                            _('Send me email when someone sends me an "@-reply".'),
                            $user->emailnotifyattn);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('emailnotifynudge',
                            // TRANS: Checkbox label in e-mail preferences form.
                            _('Allow friends to nudge me and send me an email.'),
                            $user->emailnotifynudge);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->checkbox('emailmicroid',
                            // TRANS: Checkbox label in e-mail preferences form.
                            _('Publish a MicroID for my email address.'),
                            $user->emailmicroid);
            $this->elementEnd('li');
            Event::handle('EndEmailFormData', array($this, $this->scoped));
        }
        $this->elementEnd('ul');
        // TRANS: Button label to save e-mail preferences.
        $this->submit('save', _m('BUTTON','Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Gets any existing email address confirmations we're waiting for
     *
     * @return Confirm_address Email address confirmation for user, or null
     */
    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'email';

        if ($confirm->find(true)) {
            return $confirm;
        } else {
            return null;
        }
    }

    /**
     * Handle posts
     *
     * Since there are a lot of different options on the page, we
     * figure out what we're supposed to do based on which button was
     * pushed
     *
     * @return void
     */
    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->show_form(_('There was a problem with your session token. '.
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
        } else {
            // TRANS: Message given submitting a form with an unknown action in e-mail settings.
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Save email preferences
     *
     * @return void
     */
    function savePreferences()
    {
        $user = $this->scoped->getUser();

        if (Event::handle('StartEmailSaveForm', array($this, $this->scoped))) {
            $emailnotifysub   = $this->boolean('emailnotifysub');
            $emailnotifymsg   = $this->boolean('emailnotifymsg');
            $emailnotifynudge = $this->boolean('emailnotifynudge');
            $emailnotifyattn  = $this->boolean('emailnotifyattn');
            $emailmicroid     = $this->boolean('emailmicroid');
            $emailpost        = $this->boolean('emailpost');

            $user->query('BEGIN');

            $original = clone($user);

            $user->emailnotifysub   = $emailnotifysub;
            $user->emailnotifymsg   = $emailnotifymsg;
            $user->emailnotifynudge = $emailnotifynudge;
            $user->emailnotifyattn  = $emailnotifyattn;
            $user->emailmicroid     = $emailmicroid;
            $user->emailpost        = $emailpost;

            $result = $user->update($original);

            if ($result === false) {
                common_log_db_error($user, 'UPDATE', __FILE__);
                $user->query('ROLLBACK');
                // TRANS: Server error thrown on database error updating e-mail preferences.
                $this->serverError(_('Could not update user.'));
            }

            $user->query('COMMIT');

            Event::handle('EndEmailSaveForm', array($this, $this->scoped));

            // TRANS: Confirmation message for successful e-mail preferences save.
            $this->showForm(_('Email preferences saved.'), true);
        }
    }

    /**
     * Add the address passed in by the user
     *
     * @return void
     */
    function addAddress()
    {
        $user = common_current_user();

        $email = $this->trimmed('email');

        // Some validation

        if (!$email) {
            // TRANS: Message given saving e-mail address without having provided one.
            $this->showForm(_('No email address.'));
            return;
        }

        $email = common_canonical_email($email);

        if (!$email) {
            // TRANS: Message given saving e-mail address that cannot be normalised.
            $this->showForm(_('Cannot normalize that email address.'));
            return;
        }
        if (!Validate::email($email, common_config('email', 'check_domain'))) {
            // TRANS: Message given saving e-mail address that not valid.
            $this->showForm(_('Not a valid email address.'));
            return;
        } else if ($user->email == $email) {
            // TRANS: Message given saving e-mail address that is already set.
            $this->showForm(_('That is already your email address.'));
            return;
        } else if ($this->emailExists($email)) {
            // TRANS: Message given saving e-mail address that is already set for another user.
            $this->showForm(_('That email address already belongs '.
                              'to another user.'));
            return;
        }

        if (Event::handle('StartAddEmailAddress', array($user, $email))) {

            $confirm = new Confirm_address();

            $confirm->address      = $email;
            $confirm->address_type = 'email';
            $confirm->user_id      = $user->id;
            $confirm->code         = common_confirmation_code(64);

            $result = $confirm->insert();

            if ($result === false) {
                common_log_db_error($confirm, 'INSERT', __FILE__);
                // TRANS: Server error thrown on database error adding e-mail confirmation code.
                $this->serverError(_('Could not insert confirmation code.'));
            }

            mail_confirm_address($user, $confirm->code, $user->nickname, $email);

            Event::handle('EndAddEmailAddress', array($user, $email));
        }

        // TRANS: Message given saving valid e-mail address that is to be confirmed.
        $msg = _('A confirmation code was sent to the email address you added. '.
                 'Check your inbox (and spam box!) for the code and instructions '.
                 'on how to use it.');

        $this->showForm($msg, true);
    }

    /**
     * Handle a request to cancel email confirmation
     *
     * @return void
     */
    function cancelConfirmation()
    {
        $email = $this->arg('email');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            // TRANS: Message given canceling e-mail address confirmation that is not pending.
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $email) {
            // TRANS: Message given canceling e-mail address confirmation for the wrong e-mail address.
            $this->showForm(_('That is the wrong email address.'));
            return;
        }

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error thrown on database error canceling e-mail address confirmation.
            $this->serverError(_('Could not delete email confirmation.'));
        }

        // TRANS: Message given after successfully canceling e-mail address confirmation.
        $this->showForm(_('Email confirmation cancelled.'), true);
    }

    /**
     * Handle a request to remove an address from the user's account
     *
     * @return void
     */
    function removeAddress()
    {
        $user = common_current_user();

        $email = $this->arg('email');

        // Maybe an old tab open...?

        if ($user->email != $email) {
            // TRANS: Message given trying to remove an e-mail address that is not
            // TRANS: registered for the active user.
            $this->showForm(_('That is not your email address.'));
            return;
        }

        $original = clone($user);

        $user->email = null;

        // Throws exception on failure. Also performs it within a transaction.
        $user->updateWithKeys($original);

        // TRANS: Message given after successfully removing a registered e-mail address.
        $this->showForm(_('The email address was removed.'), true);
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
        $user->emailpost = 0;

        // Throws exception on failure. Also performs it within a transaction.
        $user->updateWithKeys($orig);

        // TRANS: Message given after successfully removing an incoming e-mail address.
        $this->showForm(_('Incoming email address removed.'), true);
    }

    /**
     * Generate a new incoming email address
     *
     * @return void
     */
    function newIncoming()
    {
        $user = common_current_user();

        $orig = clone($user);

        $user->incomingemail = mail_new_incoming_address();
        $user->emailpost = 1;

        // Throws exception on failure. Also performs it within a transaction.
        $user->updateWithKeys($orig);

        // TRANS: Message given after successfully adding an incoming e-mail address.
        $this->showForm(_('New incoming email address added.'), true);
    }

    /**
     * Does another user already have this email address?
     *
     * Email addresses are unique for users.
     *
     * @param string $email Address to check
     *
     * @return boolean Whether the email already exists.
     */

    function emailExists($email)
    {
        $user = common_current_user();

        $other = User::getKV('email', $email);

        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }
}

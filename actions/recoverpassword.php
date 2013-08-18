<?php
/*
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

// You have 24 hours to claim your password

define('MAX_RECOVERY_TIME', 24 * 60 * 60);

class RecoverpasswordAction extends Action
{
    var $mode = null;
    var $msg = null;
    var $success = null;

    function handle($args)
    {
        parent::handle($args);
        if (common_logged_in()) {
            // TRANS: Client error displayed trying to recover password while already logged in.
            $this->clientError(_('You are already logged in!'));
            return;
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('recover')) {
                $this->recoverPassword();
            } else if ($this->arg('reset')) {
                $this->resetPassword();
            } else {
                // TRANS: Client error displayed when unexpected data is posted in the password recovery form.
                $this->clientError(_('Unexpected form submission.'));
            }
        } else {
            if ($this->trimmed('code')) {
                $this->checkCode();
            } else {
                $this->showForm();
            }
        }
    }

    function checkCode()
    {
        $code = $this->trimmed('code');
        $confirm = Confirm_address::getKV('code', $code);

        if (!$confirm) {
            // TRANS: Client error displayed when password recovery code is not correct.
            $this->clientError(_('No such recovery code.'));
            return;
        }
        if ($confirm->address_type != 'recover') {
            // TRANS: Client error displayed when no proper password recovery code was submitted.
            $this->clientError(_('Not a recovery code.'));
            return;
        }

        $user = User::getKV($confirm->user_id);

        if (!$user) {
            // TRANS: Server error displayed trying to recover password without providing a user.
            $this->serverError(_('Recovery code for unknown user.'));
            return;
        }

        $touched = strtotime($confirm->modified);
        $email = $confirm->address;

        // Burn this code

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error displayed removing a password recovery code from the database.
            $this->serverError(_('Error with confirmation code.'));
            return;
        }

        // These should be reaped, but for now we just check mod time
        // Note: it's still deleted; let's avoid a second attempt!

        if ((time() - $touched) > MAX_RECOVERY_TIME) {
            common_log(LOG_WARNING,
                       'Attempted redemption on recovery code ' .
                       'that is ' . $touched . ' seconds old. ');
            // TRANS: Client error displayed trying to recover password with too old a recovery code.
            $this->clientError(_('This confirmation code is too old. ' .
                                   'Please start again.'));
            return;
        }

        // If we used an outstanding confirmation to send the email,
        // it's been confirmed at this point.

        if (!$user->email) {
            $orig = clone($user);
            $user->email = $email;
            $result = $user->updateKeys($orig);
            if (!$result) {
                common_log_db_error($user, 'UPDATE', __FILE__);
                // TRANS: Server error displayed when updating a user's e-mail address in the database fails while recovering a password.
                $this->serverError(_('Could not update user with confirmed email address.'));
                return;
            }
        }

        // Success!

        $this->setTempUser($user);
        $this->showPasswordForm();
    }

    function setTempUser(&$user)
    {
        common_ensure_session();
        $_SESSION['tempuser'] = $user->id;
    }

    function getTempUser()
    {
        common_ensure_session();
        $user_id = $_SESSION['tempuser'];
        if ($user_id) {
            $user = User::getKV($user_id);
        }
        return $user;
    }

    function clearTempUser()
    {
        common_ensure_session();
        unset($_SESSION['tempuser']);
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('div', ($this->success) ? 'success' : 'error', $this->msg);
        } else {
            $this->elementStart('div', 'instructions');
            if ($this->mode == 'recover') {
                $this->element('p', null,
                               // TRANS: Page notice for password recovery page.
                               _('If you have forgotten or lost your' .
                                 ' password, you can get a new one sent to' .
                                 ' the email address you have stored' .
                                 ' in your account.'));
            } else if ($this->mode == 'reset') {
                $this->element('p', null,
                               // TRANS: Page notice for password change page.
                               _('You have been identified. Enter a' .
                                 ' new password below.'));
            }
            $this->elementEnd('div');
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->mode = 'recover';
        $this->showPage();
    }

    function showContent()
    {
        if ($this->mode == 'recover') {
            $this->showRecoverForm();
        } else if ($this->mode == 'reset') {
            $this->showResetForm();
        }
    }

    function showRecoverForm()
    {
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_password_recover',
                                           'class' => 'form_settings',
                                           'action' => common_local_url('recoverpassword')));
        $this->elementStart('fieldset');
        // TRANS: Fieldset legend for password recovery page.
        $this->element('legend', null, _('Password recovery'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label on password recovery page.
        $this->input('nicknameoremail', _('Nickname or email address'),
                     $this->trimmed('nicknameoremail'),
                     // TRANS: Title for field label on password recovery page.
                     _('Your nickname on this server, ' .
                        'or your registered email address.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->element('input', array('name' => 'recover',
                                      'type' => 'hidden',
                                      // TRANS: Field label on password recovery page.
                                      'value' => _('Recover')));
        // TRANS: Button text on password recovery page.
        $this->submit('recover', _m('BUTTON','Recover'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function title()
    {
        switch ($this->mode) {
         // TRANS: Title for password recovery page in password reset mode.
         case 'reset': return _('Reset password');
         // TRANS: Title for password recovery page in password recover mode.
         case 'recover': return _('Recover password');
         // TRANS: Title for password recovery page in email sent mode.
         case 'sent': return _('Password recovery requested');
         // TRANS: Title for password recovery page in password saved mode.
         case 'saved': return _('Password saved');
         default:
            // TRANS: Title for password recovery page when an unknown action has been specified.
            return _('Unknown action');
        }
    }

    function showPasswordForm($msg=null)
    {
        $this->msg = $msg;
        $this->mode = 'reset';
        $this->showPage();
    }

    function showResetForm()
    {
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_password_change',
                                           'class' => 'form_settings',
                                           'action' => common_local_url('recoverpassword')));
        $this->elementStart('fieldset');
         // TRANS: Fieldset legend for password reset form.
        $this->element('legend', null, _('Password change'));
        $this->hidden('token', common_session_token());
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
         // TRANS: Field label for password reset form.
        $this->password('newpassword', _('New password'),
                        // TRANS: Title for field label for password reset form.
                        _('6 or more characters, and do not forget it!'));
        $this->elementEnd('li');
        $this->elementStart('li');
         // TRANS: Field label for password reset form where the password has to be typed again.
        $this->password('confirm', _('Confirm'),
                        // TRANS: Title for field label for password reset form where the password has to be typed again.
                        _('Same as password above.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
         // TRANS: Button text for password reset form.
        $this->submit('reset', _m('BUTTON','Reset'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function recoverPassword()
    {
        $nore = $this->trimmed('nicknameoremail');

        if (!$nore) {
            // TRANS: Form instructions for password recovery form.
            $this->showForm(_('Enter a nickname or email address.'));
            return;
        }

        try {
            User::recoverPassword($nore);
            $this->mode = 'sent';
            // TRANS: User notification after an e-mail with instructions was sent from the password recovery form.
            $this->msg = _('Instructions for recovering your password ' .
                           'have been sent to the email address registered to your ' .
                           'account.');
            $this->success = true;
        } catch (Exception $e) {
            $this->success = false;
            $this->msg = $e->getMessage();
        }
        $this->showPage();
    }

    function resetPassword()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Form validation error message.
            $this->showForm(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $user = $this->getTempUser();

        if (!$user) {
            // TRANS: Client error displayed when trying to reset as password without providing a user.
            $this->clientError(_('Unexpected password reset.'));
            return;
        }

        $newpassword = $this->trimmed('newpassword');
        $confirm = $this->trimmed('confirm');

        if (!$newpassword || strlen($newpassword) < 6) {
            // TRANS: Reset password form validation error message.
            $this->showPasswordForm(_('Password must be 6 characters or more.'));
            return;
        }
        if ($newpassword != $confirm) {
            // TRANS: Reset password form validation error message.
            $this->showPasswordForm(_('Password and confirmation do not match.'));
            return;
        }

        // OK, we're ready to go

        $original = clone($user);

        $user->password = common_munge_password($newpassword, $user->id);

        if (!$user->update($original)) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Reset password form validation error message.
            $this->serverError(_('Cannot save new password.'));
            return;
        }

        $this->clearTempUser();

        if (!common_set_user($user->nickname)) {
            // TRANS: Server error displayed when something does wrong with the user object during password reset.
            $this->serverError(_('Error setting user.'));
            return;
        }

        common_real_login(true);

        $this->mode = 'saved';
        // TRANS: Success message for user after password reset.
        $this->msg = _('New password successfully saved. ' .
                       'You are now logged in.');
        $this->success = true;
        $this->showPage();
    }

    /**
     * A local menu
     *
     * Shows different login/register actions.
     *
     * @return void
     */

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}

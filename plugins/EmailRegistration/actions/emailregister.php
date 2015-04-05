<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Register a user by their email address
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
 * @category  Email registration
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Email registration
 *
 * There are four cases where we're called:
 *
 * 1. GET, no arguments. Initial registration; ask for an email address.
 * 2. POST, email address argument. Initial registration; send an email to confirm.
 * 3. GET, code argument. Confirming an invitation or a registration; look them up,
 *    create the relevant user if possible, login as that user, and
 *    show a password-entry form.
 * 4. POST, password argument. After confirmation, set the password for the new
 *    user, and redirect to a registration complete action with some instructions.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EmailregisterAction extends Action
{
    const NEWEMAIL = 1;
    const SETPASSWORD = 2;
    const NEWREGISTER = 3;
    const CONFIRMINVITE = 4;
    const CONFIRMREGISTER = 5;

    const CONFIRMTYPE = 'register';

    protected $user;
    protected $email;
    protected $code;
    protected $invitation;
    protected $confirmation;
    protected $password1;
    protected $password2;
    protected $state;
    protected $error;
    protected $complete;

    function prepare($argarray)
    {
        parent::prepare($argarray);

        if (common_config('site', 'closed')) {
            // TRANS: Client exception trown when registration by e-mail is not allowed.
            throw new ClientException(_m('Registration not allowed.'), 403);
        }

        if ($this->isPost()) {

            $this->checkSessionToken();

            $this->email = $this->trimmed('email');

            if (!empty($this->email)) {
                if (common_config('site', 'inviteonly')) {
                    // TRANS: Client exception trown when trying to register without an invitation.
                    throw new ClientException(_m('Sorry, only invited people can register.'), 403);
                }
                $this->email = common_canonical_email($this->email);
                $this->state = self::NEWEMAIL;
            } else {
                $this->state = self::SETPASSWORD;

                $this->code = $this->trimmed('code');

                if (empty($this->code)) {
                    // TRANS: Client exception thrown when no confirmation code was provided.
                    throw new ClientException(_m('No confirmation code.'));
                }

                $this->invitation = Invitation::getKV('code', $this->code);

                if (!empty($this->invitation)) {
                    if (!empty($this->invitation->registered_user_id)) {
                        // TRANS: Client exception trown when using an invitation multiple times.
                        throw new ClientException(_m('Invitation already used.'), 403);
                    }
                } else {

                    $this->confirmation = Confirm_address::getKV('code', $this->code);

                    if (empty($this->confirmation)) {
                        // TRANS: Client exception thrown when given confirmation code was not issued.
                        throw new ClientException(_m('No such confirmation code.'), 403);
                    }
                }

                $this->nickname = Nickname::normalize($this->trimmed('nickname'));
                $this->password1 = $this->trimmed('password1');
                $this->password2 = $this->trimmed('password2');

                $this->tos = $this->boolean('tos');
            }
        } else { // GET
            $this->code = $this->trimmed('code');

            if (empty($this->code)) {
                if (common_config('site', 'inviteonly')) {
                    // TRANS: Client exception trown when trying to register without an invitation.
                    throw new ClientException(_m('Sorry, only invited people can register.'), 403);
                }
                $this->state = self::NEWREGISTER;
            } else {
                $this->invitation = Invitation::getKV('code', $this->code);
                if (!empty($this->invitation)) {
                    if (!empty($this->invitation->registered_user_id)) {
                        // TRANS: Client exception trown when using an invitation multiple times.
                        throw new ClientException(_m('Invitation already used.'), 403);
                    }
                    $this->state = self::CONFIRMINVITE;
                } else {
                    $this->state = self::CONFIRMREGISTER;
                    $this->confirmation = Confirm_address::getKV('code', $this->code);

                    if (empty($this->confirmation)) {
                        // TRANS: Client exception thrown when given confirmation code was not issued.
                        throw new ClientException(_m('No such confirmation code.'), 405);
                    }
                }
            }
        }

        return true;
    }

    function title()
    {
        switch ($this->state) {
        case self::NEWREGISTER:
        case self::NEWEMAIL:
            // TRANS: Title for registration page.
            return _m('TITLE','Register');
            break;
        case self::SETPASSWORD:
        case self::CONFIRMINVITE:
        case self::CONFIRMREGISTER:
            // TRANS: Title for page where to register with a confirmation code.
            return _m('TITLE','Complete registration');
            break;
        }
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
        $cur = common_current_user();

        if (!empty($cur)) {
            common_redirect(common_local_url('all', array('nickname' => $cur->nickname)));
        }

        switch ($this->state) {
        case self::NEWREGISTER:
            $this->showRegistrationForm();
            break;
        case self::NEWEMAIL:
            $this->registerUser();
            break;
        case self::CONFIRMINVITE:
            $this->confirmRegistration();
            break;
        case self::CONFIRMREGISTER:
            $this->confirmRegistration();
            break;
        case self::SETPASSWORD:
            $this->setPassword();
            break;
        }
        return;
    }

    function showRegistrationForm()
    {
        $this->form = new EmailRegistrationForm($this, $this->email);
        $this->showPage();
    }

    function registerUser()
    {
        try {
            $confirm = EmailRegistrationPlugin::registerEmail($this->email);
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showRegistrationForm();
            return;
        }

        EmailRegistrationPlugin::sendConfirmEmail($confirm);

        // TRANS: Confirmation text after initial registration.
        // TRANS: %s an e-mail address.
        $prompt = sprintf(_m('An email was sent to %s to confirm that address. Check your email inbox for instructions.'),
                          $this->email);

        $this->complete = $prompt;

        $this->showPage();
    }

    function confirmRegistration()
    {
        if (!empty($this->invitation)) {
            $email = $this->invitation->address;
        } else if (!empty($this->confirmation)) {
            $email = $this->confirmation->address;
        }

        $nickname = $this->nicknameFromEmail($email);

        $this->form = new ConfirmRegistrationForm($this,
                                                  $nickname,
                                                  $email,
                                                  $this->code);
        $this->showPage();
    }

    function setPassword()
    {
        if (Event::handle('StartRegistrationTry', array($this))) {
            if (!empty($this->invitation)) {
                $email = trim($this->invitation->address);
            } else if (!empty($this->confirmation)) {
                $email = trim($this->confirmation->address);
            } else {
                // TRANS: Client exception trown when trying to set password with an invalid confirmation code.
                throw new Exception(_m('No confirmation thing.'));
            }

            if (!$this->tos) {
                // TRANS: Error text when trying to register without agreeing to the terms.
                $this->error = _m('You must accept the terms of service and privacy policy to register.');
            } else if (empty($this->password1)) {
                // TRANS: Error text when trying to register without a password.
                $this->error = _m('You must set a password');
            } else if (strlen($this->password1) < 6) {
                // TRANS: Error text when trying to register with too short a password.
                $this->error = _m('Password must be 6 or more characters.');
            } else if ($this->password1 != $this->password2) {
                // TRANS: Error text when trying to register without providing the same password twice.
                $this->error = _m('Passwords do not match.');
            }

            if (!empty($this->error)) {
                $this->form = new ConfirmRegistrationForm($this, $this->nickname, $email, $this->code);
                $this->showPage();
                return;
            }

            try {
                $fields = array('nickname' => $this->nickname,
                                'email' => $email,
                                'password' => $this->password1,
                                'email_confirmed' => true);

                if (!empty($this->invitation)) {
                    $fields['code'] = $this->invitation->code;
                }
                $this->user = User::register($fields);
            } catch (ClientException $e) {
                $this->error = $e->getMessage();
                $this->form = new ConfirmRegistrationForm($this, $this->nickname, $email, $this->code);
                $this->showPage();
                return;
            }

            if (empty($this->user)) {
                // TRANS: Exception trown when using an invitation multiple times.
                throw new Exception(_m('Failed to register user.'));
            }

            common_set_user($this->user);
            // this is a real login
            common_real_login(true);

            // Re-init language env in case it changed (not yet, but soon)
            common_init_language();

            if (!empty($this->confirmation)) {
                $this->confirmation->delete();
            }

            Event::handle('EndRegistrationTry', array($this));
        }

        if (Event::handle('StartRegisterSuccess', array($this))) {
            Event::handle('EndRegisterSuccess', array($this));
            common_redirect(common_local_url('doc', array('title' => 'welcome')), 303);
            // common_redirect exits, so we can't run the event _after_ it of course.
        }
    }

    function sendConfirmEmail($confirm)
    {
        $sitename = common_config('site', 'name');

        $recipients = array($confirm->address);

        $headers['From'] = mail_notify_from();
        $headers['To'] = trim($confirm->address);
         // TRANS: Subject for confirmation e-mail.
         // TRANS: %s is the StatusNet sitename.
        $headers['Subject'] = sprintf(_m('Confirm your registration on %s'), $sitename);

        $confirmUrl = common_local_url('register', array('code' => $confirm->code));

        // TRANS: Body for confirmation e-mail.
        // TRANS: %1$s is the StatusNet sitename, %2$s is the confirmation URL.
        $body = sprintf(_m('Someone (probably you) has requested an account on %1$s using this email address.'.
                          "\n".
                          'To confirm the address, click the following URL or copy it into the address bar of your browser.'.
                          "\n".
                          '%2$s'.
                          "\n".
                          'If it was not you, you can safely ignore this message.'),
                        $sitename,
                        $confirmUrl);

        mail_send($recipients, $headers, $body);
    }

    function showContent()
    {
        if ($this->complete) {
            $this->elementStart('p', 'success');
            $this->raw($this->complete);
            $this->elementEnd('p');
        } else {
            if ($this->error) {
                $this->elementStart('p', 'error');
                $this->raw($this->error);
                $this->elementEnd('p');
            }

            if (!empty($this->form)) {
                $this->form->show();
            }
        }
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

    function nicknameFromEmail($email)
    {
        return EmailRegistrationPlugin::nicknameFromEmail($email);
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

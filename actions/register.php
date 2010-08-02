<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Register a new user account
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
 * @category  Login
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
 * An action for registering a new user account
 *
 * @category Login
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class RegisterAction extends Action
{
    /**
     * Has there been an error?
     */

    var $error = null;

    /**
     * Have we registered?
     */

    var $registered = false;

    /**
     * Are we processing an invite?
     */

    var $invite = null;

    /**
     * Prepare page to run
     *
     *
     * @param $args
     * @return string title
     */

    function prepare($args)
    {
        parent::prepare($args);
        $this->code = $this->trimmed('code');

        // @todo this check should really be in index.php for all sensitive actions
        $ssl = common_config('site', 'ssl');
        if (empty($_SERVER['HTTPS']) && ($ssl == 'always' || $ssl == 'sometimes')) {
            common_redirect(common_local_url('register'));
            // exit
        }

        if (empty($this->code)) {
            common_ensure_session();
            if (array_key_exists('invitecode', $_SESSION)) {
                $this->code = $_SESSION['invitecode'];
            }
        }

        if (common_config('site', 'inviteonly') && empty($this->code)) {
            $this->clientError(_('Sorry, only invited people can register.'));
            return false;
        }

        if (!empty($this->code)) {
            $this->invite = Invitation::staticGet('code', $this->code);
            if (empty($this->invite)) {
                $this->clientError(_('Sorry, invalid invitation code.'));
                return false;
            }
            // Store this in case we need it
            common_ensure_session();
            $_SESSION['invitecode'] = $this->code;
        }

        return true;
    }

    /**
     * Title of the page
     *
     * @return string title
     */

    function title()
    {
        if ($this->registered) {
            return _('Registration successful');
        } else {
            return _('Register');
        }
    }

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * Checks if registration is closed and shows an error if so.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if (common_config('site', 'closed')) {
            $this->clientError(_('Registration not allowed.'));
        } else if (common_logged_in()) {
            $this->clientError(_('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->tryRegister();
        } else {
            $this->showForm();
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('nickname');
    }

    /**
     * Try to register a user
     *
     * Validates the input and tries to save a new user and profile
     * record. On success, shows an instructions page.
     *
     * @return void
     */

    function tryRegister()
    {
        if (Event::handle('StartRegistrationTry', array($this))) {
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }

            $nickname = $this->trimmed('nickname');
            $email    = $this->trimmed('email');
            $fullname = $this->trimmed('fullname');
            $homepage = $this->trimmed('homepage');
            $bio      = $this->trimmed('bio');
            $location = $this->trimmed('location');

            // We don't trim these... whitespace is OK in a password!
            $password = $this->arg('password');
            $confirm  = $this->arg('confirm');

            // invitation code, if any
            $code = $this->trimmed('code');

            if ($code) {
                $invite = Invitation::staticGet($code);
            }

            if (common_config('site', 'inviteonly') && !($code && $invite)) {
                $this->clientError(_('Sorry, only invited people can register.'));
                return;
            }

            // Input scrubbing
            $nickname = common_canonical_nickname($nickname);
            $email    = common_canonical_email($email);

            if (!$this->boolean('license')) {
                $this->showForm(_('You can\'t register if you don\'t '.
                                  'agree to the license.'));
            } else if ($email && !Validate::email($email, common_config('email', 'check_domain'))) {
                $this->showForm(_('Not a valid email address.'));
            } else if (!Validate::string($nickname, array('min_length' => 1,
                                                          'max_length' => 64,
                                                          'format' => NICKNAME_FMT))) {
                $this->showForm(_('Nickname must have only lowercase letters '.
                                  'and numbers and no spaces.'));
            } else if ($this->nicknameExists($nickname)) {
                $this->showForm(_('Nickname already in use. Try another one.'));
            } else if (!User::allowed_nickname($nickname)) {
                $this->showForm(_('Not a valid nickname.'));
            } else if ($this->emailExists($email)) {
                $this->showForm(_('Email address already exists.'));
            } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !Validate::uri($homepage,
                                      array('allowed_schemes' =>
                                            array('http', 'https')))) {
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                $this->showForm(_('Full name is too long (max 255 chars).'));
                return;
            } else if (Profile::bioTooLong($bio)) {
                $this->showForm(sprintf(_('Bio is too long (max %d chars).'),
                                        Profile::maxBio()));
                return;
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                $this->showForm(_('Location is too long (max 255 chars).'));
                return;
            } else if (strlen($password) < 6) {
                $this->showForm(_('Password must be 6 or more characters.'));
                return;
            } else if ($password != $confirm) {
                $this->showForm(_('Passwords don\'t match.'));
            } else if ($user = User::register(array('nickname' => $nickname,
                                                    'password' => $password,
                                                    'email' => $email,
                                                    'fullname' => $fullname,
                                                    'homepage' => $homepage,
                                                    'bio' => $bio,
                                                    'location' => $location,
                                                    'code' => $code))) {
                if (!$user) {
                    $this->showForm(_('Invalid username or password.'));
                    return;
                }
                // success!
                if (!common_set_user($user)) {
                    $this->serverError(_('Error setting user.'));
                    return;
                }
                // this is a real login
                common_real_login(true);
                if ($this->boolean('rememberme')) {
                    common_debug('Adding rememberme cookie for ' . $nickname);
                    common_rememberme($user);
                }

                Event::handle('EndRegistrationTry', array($this));

                // Re-init language env in case it changed (not yet, but soon)
                common_init_language();

                $this->showSuccess();
            } else {
                $this->showForm(_('Invalid username or password.'));
            }
        }
    }

    /**
     * Does the given nickname already exist?
     *
     * Checks a canonical nickname against the database.
     *
     * @param string $nickname nickname to check
     *
     * @return boolean true if the nickname already exists
     */

    function nicknameExists($nickname)
    {
        $user = User::staticGet('nickname', $nickname);
        return is_object($user);
    }

    /**
     * Does the given email address already exist?
     *
     * Checks a canonical email address against the database.
     *
     * @param string $email email address to check
     *
     * @return boolean true if the address already exists
     */

    function emailExists($email)
    {
        $email = common_canonical_email($email);
        if (!$email || strlen($email) == 0) {
            return false;
        }
        $user = User::staticGet('email', $email);
        return is_object($user);
    }

    // overrrided to add entry-title class
    function showPageTitle() {
        if (Event::handle('StartShowPageTitle', array($this))) {
            $this->element('h1', array('class' => 'entry-title'), $this->title());
        }
    }

    // overrided to add hentry, and content-inner class
    function showContentBlock()
    {
        $this->elementStart('div', array('id' => 'content', 'class' => 'hentry'));
        $this->showPageTitle();
        $this->showPageNoticeBlock();
        $this->elementStart('div', array('id' => 'content_inner',
                                         'class' => 'entry-content'));
        // show the actual content (forms, lists, whatever)
        $this->showContent();
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Instructions or a notice for the page
     *
     * Shows the error, if any, or instructions for registration.
     *
     * @return void
     */

    function showPageNotice()
    {
        if ($this->registered) {
            return;
        } else if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $instr =
              common_markup_to_html(_('With this form you can create '.
                                      'a new account. ' .
                                      'You can then post notices and '.
                                      'link up to friends and colleagues. '));

            $this->elementStart('div', 'instructions');
            $this->raw($instr);
            $this->elementEnd('div');
        }
    }

    /**
     * Wrapper for showing a page
     *
     * Stores an error and shows the page
     *
     * @param string $error Error, if any
     *
     * @return void
     */

    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    /**
     * Show the page content
     *
     * Either shows the registration form or, if registration was successful,
     * instructions for using the site.
     *
     * @return void
     */

    function showContent()
    {
        if ($this->registered) {
            $this->showSuccessContent();
        } else {
            $this->showFormContent();
        }
    }

    /**
     * Show the registration form
     *
     * @return void
     */

    function showFormContent()
    {
        $code = $this->trimmed('code');

        $invite = null;

        if ($code) {
            $invite = Invitation::staticGet($code);
        }

        if (common_config('site', 'inviteonly') && !($code && $invite)) {
            $this->clientError(_('Sorry, only invited people can register.'));
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_register',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('register')));
        $this->elementStart('fieldset');
        $this->element('legend', null, 'Account settings');
        $this->hidden('token', common_session_token());

        if ($this->code) {
            $this->hidden('code', $this->code);
        }

        $this->elementStart('ul', 'form_data');
        if (Event::handle('StartRegistrationFormData', array($this))) {
            $this->elementStart('li');
            $this->input('nickname', _('Nickname'), $this->trimmed('nickname'),
                         _('1-64 lowercase letters or numbers, '.
                           'no punctuation or spaces. Required.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->password('password', _('Password'),
                            _('6 or more characters. Required.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->password('confirm', _('Confirm'),
                            _('Same as password above. Required.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            if ($this->invite && $this->invite->address_type == 'email') {
                $this->input('email', _('Email'), $this->invite->address,
                             _('Used only for updates, announcements, '.
                               'and password recovery'));
            } else {
                $this->input('email', _('Email'), $this->trimmed('email'),
                             _('Used only for updates, announcements, '.
                               'and password recovery'));
            }
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->input('fullname', _('Full name'),
                         $this->trimmed('fullname'),
                         _('Longer name, preferably your "real" name'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->input('homepage', _('Homepage'),
                         $this->trimmed('homepage'),
                         _('URL of your homepage, blog, '.
                           'or profile on another site'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $maxBio = Profile::maxBio();
            if ($maxBio > 0) {
                $bioInstr = sprintf(_('Describe yourself and your interests in %d chars'),
                                    $maxBio);
            } else {
                $bioInstr = _('Describe yourself and your interests');
            }
            $this->textarea('bio', _('Bio'),
                            $this->trimmed('bio'),
                            $bioInstr);
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->input('location', _('Location'),
                         $this->trimmed('location'),
                         _('Where you are, like "City, '.
                           'State (or Region), Country"'));
            $this->elementEnd('li');
            Event::handle('EndRegistrationFormData', array($this));
            $this->elementStart('li', array('id' => 'settings_rememberme'));
            $this->checkbox('rememberme', _('Remember me'),
                            $this->boolean('rememberme'),
                            _('Automatically login in the future; '.
                              'not for shared computers!'));
            $this->elementEnd('li');
            $attrs = array('type' => 'checkbox',
                           'id' => 'license',
                           'class' => 'checkbox',
                           'name' => 'license',
                           'value' => 'true');
            if ($this->boolean('license')) {
                $attrs['checked'] = 'checked';
            }
            $this->elementStart('li');
            $this->element('input', $attrs);
            $this->elementStart('label', array('class' => 'checkbox', 'for' => 'license'));
            $this->raw($this->licenseCheckbox());
            $this->elementEnd('label');
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
        $this->submit('submit', _('Register'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function licenseCheckbox()
    {
        $out = '';
        switch (common_config('license', 'type')) {
        case 'private':
            // TRANS: Copyright checkbox label in registration dialog, for private sites.
            $out .= htmlspecialchars(sprintf(
                _('I understand that content and data of %1$s are private and confidential.'),
                common_config('site', 'name')));
            // fall through
        case 'allrightsreserved':
            if ($out != '') {
                $out .= ' ';
            }
            if (common_config('license', 'owner')) {
                // TRANS: Copyright checkbox label in registration dialog, for all rights reserved with a specified copyright owner.
                $out .= htmlspecialchars(sprintf(
                    _('My text and files are copyright by %1$s.'),
                    common_config('license', 'owner')));
            } else {
                // TRANS: Copyright checkbox label in registration dialog, for all rights reserved with ownership left to contributors.
                $out .= htmlspecialchars(_('My text and files remain under my own copyright.'));
            }
            // TRANS: Copyright checkbox label in registration dialog, for all rights reserved.
            $out .= ' ' . _('All rights reserved.');
            break;
        case 'cc': // fall through
        default:
            // TRANS: Copyright checkbox label in registration dialog, for Creative Commons-style licenses.
            $message = _('My text and files are available under %s ' .
                         'except this private data: password, ' .
                         'email address, IM address, and phone number.');
            $link = '<a href="' .
                    htmlspecialchars(common_config('license', 'url')) .
                    '">' .
                    htmlspecialchars(common_config('license', 'title')) .
                    '</a>';
            $out .= sprintf(htmlspecialchars($message), $link);
        }
        return $out;
    }

    /**
     * Show some information about registering for the site
     *
     * Save the registration flag, run showPage
     *
     * @return void
     */

    function showSuccess()
    {
        $this->registered = true;
        $this->showPage();
    }

    /**
     * Show some information about registering for the site
     *
     * Gives some information and options for new registrees.
     *
     * @return void
     */

    function showSuccessContent()
    {
        $nickname = $this->arg('nickname');

        $profileurl = common_local_url('showstream',
                                       array('nickname' => $nickname));

        $this->elementStart('div', 'success');
        $instr = sprintf(_('Congratulations, %1$s! And welcome to %%%%site.name%%%%. '.
                           'From here, you may want to...'. "\n\n" .
                           '* Go to [your profile](%2$s) '.
                           'and post your first message.' .  "\n" .
                           '* Add a [Jabber/GTalk address]'.
                           '(%%%%action.imsettings%%%%) '.
                           'so you can send notices '.
                           'through instant messages.' . "\n" .
                           '* [Search for people](%%%%action.peoplesearch%%%%) '.
                           'that you may know or '.
                           'that share your interests. ' . "\n" .
                           '* Update your [profile settings]'.
                           '(%%%%action.profilesettings%%%%)'.
                           ' to tell others more about you. ' . "\n" .
                           '* Read over the [online docs](%%%%doc.help%%%%)'.
                           ' for features you may have missed. ' . "\n\n" .
                           'Thanks for signing up and we hope '.
                           'you enjoy using this service.'),
                         $nickname, $profileurl);

        $this->raw(common_markup_to_html($instr));

        $have_email = $this->trimmed('email');
        if ($have_email) {
            $emailinstr = _('(You should receive a message by email '.
                            'momentarily, with ' .
                            'instructions on how to confirm '.
                            'your email address.)');
            $this->raw(common_markup_to_html($emailinstr));
        }
        $this->elementEnd('div');
    }

    /**
     * Show the login group nav menu
     *
     * @return void
     */

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}


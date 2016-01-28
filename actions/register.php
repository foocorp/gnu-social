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

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

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
    protected function prepare(array $args=array())
    {
        parent::prepare($args);
        $this->code = $this->trimmed('code');

        if (empty($this->code)) {
            common_ensure_session();
            if (array_key_exists('invitecode', $_SESSION)) {
                $this->code = $_SESSION['invitecode'];
            }
        }

        if (common_config('site', 'inviteonly') && empty($this->code)) {
            // TRANS: Client error displayed when trying to register to an invite-only site without an invitation.
            $this->clientError(_('Sorry, only invited people can register.'));
        }

        if (!empty($this->code)) {
            $this->invite = Invitation::getKV('code', $this->code);
            if (!$this->invite instanceof Invitation) {
            // TRANS: Client error displayed when trying to register to an invite-only site without a valid invitation.
                $this->clientError(_('Sorry, invalid invitation code.'));
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
            // TRANS: Title for registration page after a succesful registration.
            return _('Registration successful');
        } else {
            // TRANS: Title for registration page.
            return _m('TITLE','Register');
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
            // TRANS: Client error displayed when trying to register to a closed site.
            $this->clientError(_('Registration not allowed.'));
        } else if (common_logged_in()) {
            // TRANS: Client error displayed when trying to register while already logged in.
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
                // TRANS: Client error displayed when the session token does not match or is not given.
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
                $invite = Invitation::getKV($code);
            }

            if (common_config('site', 'inviteonly') && !($code && $invite)) {
                // TRANS: Client error displayed when trying to register to an invite-only site without an invitation.
                $this->clientError(_('Sorry, only invited people can register.'));
            }

            // Input scrubbing
            try {
                $nickname = Nickname::normalize($nickname, true);
            } catch (NicknameException $e) {
                $this->showForm($e->getMessage());
                return;
            }
            $email    = common_canonical_email($email);

            if (!$this->boolean('license')) {
                // TRANS: Form validation error displayed when trying to register without agreeing to the site license.
                $this->showForm(_('You cannot register if you do not '.
                                  'agree to the license.'));
            } else if ($email && !Validate::email($email, common_config('email', 'check_domain'))) {
                // TRANS: Form validation error displayed when trying to register without a valid e-mail address.
                $this->showForm(_('Not a valid email address.'));
            } else if ($this->emailExists($email)) {
                // TRANS: Form validation error displayed when trying to register with an already registered e-mail address.
                $this->showForm(_('Email address already exists.'));
            } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !common_valid_http_url($homepage)) {
                // TRANS: Form validation error displayed when trying to register with an invalid homepage URL.
                $this->showForm(_('Homepage is not a valid URL.'));
            } else if (Profile::bioTooLong($bio)) {
                // TRANS: Form validation error on registration page when providing too long a bio text.
                // TRANS: %d is the maximum number of characters for bio; used for plural.
                $this->showForm(sprintf(_m('Bio is too long (maximum %d character).',
                                           'Bio is too long (maximum %d characters).',
                                           Profile::maxBio()),
                                        Profile::maxBio()));
            } else if (strlen($password) < 6) {
                // TRANS: Form validation error displayed when trying to register with too short a password.
                $this->showForm(_('Password must be 6 or more characters.'));
            } else if ($password != $confirm) {
                // TRANS: Form validation error displayed when trying to register with non-matching passwords.
                $this->showForm(_('Passwords do not match.'));
            } else {
                try {
                    $user = User::register(array('nickname' => $nickname,
                                                    'password' => $password,
                                                    'email' => $email,
                                                    'fullname' => $fullname,
                                                    'homepage' => $homepage,
                                                    'bio' => $bio,
                                                    'location' => $location,
                                                    'code' => $code));
                    // success!
                    if (!common_set_user($user)) {
                        // TRANS: Server error displayed when saving fails during user registration.
                        $this->serverError(_('Error setting user.'));
                    }
                    // this is a real login
                    common_real_login(true);
                    if ($this->boolean('rememberme')) {
                        common_debug('Adding rememberme cookie for ' . $nickname);
                        common_rememberme($user);
                    }

                    // Re-init language env in case it changed (not yet, but soon)
                    common_init_language();

                    Event::handle('EndRegistrationTry', array($this));

                    $this->showSuccess();
                } catch (Exception $e) {
                    // TRANS: Form validation error displayed when trying to register with an invalid username or password.
                    $this->showForm($e->getMessage());
                }
            }
        }
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
        $user = User::getKV('email', $email);
        return is_object($user);
    }

    // overrrided to add entry-title class
    function showPageTitle() {
        if (Event::handle('StartShowPageTitle', array($this))) {
            $this->element('h1', array('class' => 'entry-title'), $this->title());
        }
    }

    // overrided to add h-entry, and content-inner class
    function showContentBlock()
    {
        $this->elementStart('div', array('id' => 'content', 'class' => 'h-entry'));
        $this->showPageTitle();
        $this->showPageNoticeBlock();
        $this->elementStart('div', array('id' => 'content_inner',
                                         'class' => 'e-content'));
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
              // TRANS: Page notice on registration page.
              common_markup_to_html(_('With this form you can create '.
                                      'a new account. ' .
                                      'You can then post notices and '.
                                      'link up to friends and colleagues.'));

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
            $invite = Invitation::getKV($code);
        }

        if (common_config('site', 'inviteonly') && !($code && $invite)) {
            // TRANS: Client error displayed when trying to register to an invite-only site without an invitation.
            $this->clientError(_('Sorry, only invited people can register.'));
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_register',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('register')));
        $this->elementStart('fieldset');
        // TRANS: Fieldset legend on accout registration page.
        $this->element('legend', null, 'Account settings');
        $this->hidden('token', common_session_token());

        if ($this->code) {
            $this->hidden('code', $this->code);
        }

        $this->elementStart('ul', 'form_data');
        if (Event::handle('StartRegistrationFormData', array($this))) {
            $this->elementStart('li');
            // TRANS: Field label on account registration page.
            $this->input('nickname', _('Nickname'), $this->trimmed('nickname'),
                         // TRANS: Field title on account registration page.
                         _('1-64 lowercase letters or numbers, no punctuation or spaces.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label on account registration page.
            $this->password('password', _('Password'),
                            // TRANS: Field title on account registration page.
                            _('6 or more characters.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label on account registration page. In this field the password has to be entered a second time.
            $this->password('confirm', _m('PASSWORD','Confirm'),
                         // TRANS: Field title on account registration page.
                         _('Same as password above.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            if ($this->invite && $this->invite->address_type == 'email') {
                // TRANS: Field label on account registration page.
                $this->input('email', _m('LABEL','Email'), $this->invite->address,
                             // TRANS: Field title on account registration page.
                             _('Used only for updates, announcements, '.
                               'and password recovery.'));
            } else {
                // TRANS: Field label on account registration page.
                $this->input('email', _m('LABEL','Email'), $this->trimmed('email'),
                             // TRANS: Field title on account registration page.
                             _('Used only for updates, announcements, '.
                               'and password recovery.'));
            }
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label on account registration page.
            $this->input('fullname', _('Full name'),
                         $this->trimmed('fullname'),
                     // TRANS: Field title on account registration page.
                     _('Longer name, preferably your "real" name.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label on account registration page.
            $this->input('homepage', _('Homepage'),
                         $this->trimmed('homepage'),
                         // TRANS: Field title on account registration page.
                         _('URL of your homepage, blog, '.
                           'or profile on another site.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $maxBio = Profile::maxBio();
            if ($maxBio > 0) {
                // TRANS: Text area title in form for account registration. Plural
                // TRANS: is decided by the number of characters available for the
                // TRANS: biography (%d).
                $bioInstr = sprintf(_m('Describe yourself and your interests in %d character.',
                                       'Describe yourself and your interests in %d characters.',
                                       $maxBio),
                                    $maxBio);
            } else {
                // TRANS: Text area title on account registration page.
                $bioInstr = _('Describe yourself and your interests.');
            }
            // TRANS: Text area label on account registration page.
            $this->textarea('bio', _('Bio'),
                            $this->trimmed('bio'),
                            $bioInstr);
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label on account registration page.
            $this->input('location', _('Location'),
                         $this->trimmed('location'),
                         // TRANS: Field title on account registration page.
                         _('Where you are, like "City, '.
                           'State (or Region), Country".'));
            $this->elementEnd('li');
            Event::handle('EndRegistrationFormData', array($this));
            $this->elementStart('li', array('id' => 'settings_rememberme'));
            // TRANS: Checkbox label on account registration page.
            $this->checkbox('rememberme', _('Remember me'),
                            $this->boolean('rememberme'),
                            // TRANS: Checkbox title on account registration page.
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
        // TRANS: Button text to register a user on account registration page.
        $this->submit('submit', _m('BUTTON','Register'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function licenseCheckbox()
    {
        $out = '';
        switch (common_config('license', 'type')) {
        case 'private':
            $out .= htmlspecialchars(sprintf(
                // TRANS: Copyright checkbox label in registration dialog, for private sites.
                // TRANS: %1$s is the StatusNet sitename.
                _('I understand that content and data of %1$s are private and confidential.'),
                common_config('site', 'name')));
            // fall through
        case 'allrightsreserved':
            if ($out != '') {
                $out .= ' ';
            }
            if (common_config('license', 'owner')) {
                $out .= htmlspecialchars(sprintf(
                    // TRANS: Copyright checkbox label in registration dialog, for all rights reserved with a specified copyright owner.
                    // TRANS: %1$s is the license owner.
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
        if (Event::handle('StartRegisterSuccess', array($this))) {
            $nickname = $this->arg('nickname');

            $profileurl = common_local_url('showstream',
                                           array('nickname' => $nickname));

            $this->elementStart('div', 'success');
            // TRANS: Text displayed after successful account registration.
            // TRANS: %1$s is the registered nickname, %2$s is the profile URL.
            // TRANS: This message contains Markdown links in the form [link text](link)
            // TRANS: and variables in the form %%%%variable%%%%. Please mind the syntax.
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
                // TRANS: Instruction text on how to deal with the e-mail address confirmation e-mail.
                $emailinstr = _('(You should receive a message by email '.
                                'momentarily, with ' .
                                'instructions on how to confirm '.
                                'your email address.)');
                $this->raw(common_markup_to_html($emailinstr));
            }
            $this->elementEnd('div');

            Event::handle('EndRegisterSuccess', array($this));
        }
    }

    /**
     * Show the login group nav menu
     *
     * @return void
     */
    function showLocalNav()
    {
        if (common_logged_in()) {
            parent::showLocalNav();
        } else {
            $nav = new LoginGroupNav($this);
            $nav->show();
        }
    }

    /**
     * Show a bit of login context
     *
     * @return nothing
     */
    function showProfileBlock()
    {
        if (common_logged_in()) {
            parent::showProfileBlock();
        }
    }
}

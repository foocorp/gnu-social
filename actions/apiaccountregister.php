<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Register account
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
 * @category  API
 * @package   GNUsocial
 * @author    Hannes Mannerheim <h@nnesmannerhe.im>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ApiAccountRegisterAction extends ApiAction
{

    /**
     * Has there been an error?
     */
    var $error = null;

    /**
     * Have we registered?
     */
    var $registered = false;

    protected $needPost = true;

    protected $code   = null; // invite code
    protected $invite = null; // invite to-be-stored

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if ($this->format !== 'json') {
            $this->clientError('This method currently only serves JSON.', 415);
        }

        $this->code = $this->trimmed('code');

        return true;
    }

    /**
     * Handle the request
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        $nickname = $this->trimmed('nickname');
        $email    = $this->trimmed('email');
        $fullname = $this->trimmed('fullname');
        $homepage = $this->trimmed('homepage');
        $bio      = $this->trimmed('bio');
        $location = $this->trimmed('location');

        // We don't trim these... whitespace is OK in a password!
        $password = $this->arg('password');
        $confirm  = $this->arg('confirm');

        if (empty($this->code)) {
            common_ensure_session();
            if (array_key_exists('invitecode', $_SESSION)) {
                $this->code = $_SESSION['invitecode'];
            }
        }

        if (common_config('site', 'inviteonly') && empty($this->code)) {
            // TRANS: Client error displayed when trying to register to an invite-only site without an invitation.
            $this->clientError(_('Sorry, only invited people can register.'), 401);
        }

        if (!empty($this->code)) {
            $this->invite = Invitation::getKV('code', $this->code);
            if (empty($this->invite)) {
            // TRANS: Client error displayed when trying to register to an invite-only site without a valid invitation.
	            $this->clientError(_('Sorry, invalid invitation code.'), 401);
            }
            // Store this in case we need it
            common_ensure_session();
            $_SESSION['invitecode'] = $this->code;
        }

        // Input scrubbing
        try {
            $nickname = Nickname::normalize($nickname, true);
        } catch (NicknameException $e) {
            // clientError handles Api exceptions with various formats and stuff
	        $this->clientError($e->getMessage(), $e->getCode());
        }

        $email = common_canonical_email($email);

	    if ($email && !Validate::email($email, common_config('email', 'check_domain'))) {
            // TRANS: Form validation error displayed when trying to register without a valid e-mail address.
	        $this->clientError(_('Not a valid email address.'), 400);
        } else if ($this->emailExists($email)) {
            // TRANS: Form validation error displayed when trying to register with an already registered e-mail address.
	        $this->clientError(_('Email address already exists.'), 400);
        } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                   !common_valid_http_url($homepage)) {
            // TRANS: Form validation error displayed when trying to register with an invalid homepage URL.
	        $this->clientError(_('Homepage is not a valid URL.'), 400);
        } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
            // TRANS: Form validation error displayed when trying to register with a too long full name.
	        $this->clientError(_('Full name is too long (maximum 255 characters).'), 400);
        } else if (Profile::bioTooLong($bio)) {
            // TRANS: Form validation error on registration page when providing too long a bio text.
            // TRANS: %d is the maximum number of characters for bio; used for plural.
	        $this->clientError(sprintf(_m('Bio is too long (maximum %d character).',
                                       'Bio is too long (maximum %d characters).',
                                       Profile::maxBio()),
                                       Profile::maxBio()), 400);
        } else if (!is_null($location) && mb_strlen($location) > 255) {
            // TRANS: Form validation error displayed when trying to register with a too long location.
	        $this->clientError(_('Location is too long (maximum 255 characters).'), 400);
        } else if (strlen($password) < 6) {
            // TRANS: Form validation error displayed when trying to register with too short a password.
	        $this->clientError(_('Password must be 6 or more characters.'), 400);
        } else if ($password != $confirm) {
            // TRANS: Form validation error displayed when trying to register with non-matching passwords.
	        $this->clientError(_('Passwords do not match.'), 400);
        } else {

            // annoy spammers
            sleep(7);
            
			if (Event::handle('APIStartRegistrationTry', array($this))) { 
				try {
					$user = User::register(array('nickname' => $nickname,
														'password' => $password,
														'email' => $email,
														'fullname' => $fullname,
														'homepage' => $homepage,
														'bio' => $bio,
														'location' => $location,
														'code' => $this->code));
					Event::handle('EndRegistrationTry', array($this));

					$this->initDocument('json');
					$this->showJsonObjects($this->twitterUserArray($user->getProfile()));
					$this->endDocument('json');

				} catch (Exception $e) {
					$this->clientError($e->getMessage(), 400);
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

}
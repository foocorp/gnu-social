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
 * @package   GNUSocial
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

    /**
     * Are we processing an invite?
     */
    var $invite = null;           

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
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
            $this->clientError(_('Sorry, only invited people can register.'),404,'json');
            return false;
        }

        if (!empty($this->code)) {
            $this->invite = Invitation::staticGet('code', $this->code);
            if (empty($this->invite)) {
            // TRANS: Client error displayed when trying to register to an invite-only site without a valid invitation.
	            $this->clientError(_('Sorry, invalid invitation code.'),404,'json');	
                return false;
            }
            // Store this in case we need it
            common_ensure_session();
            $_SESSION['invitecode'] = $this->code;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                _('This method requires a POST.'),
                400, $this->format
            );
            return;

        } else {
	
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
                // TRANS: Client error displayed when trying to register to an invite-only site without an invitation.
	            $this->clientError(_('Sorry, only invited people can register.'),404,'json');	
                return;
            }

            // Input scrubbing
            try {
                $nickname = Nickname::normalize($nickname);
            } catch (NicknameException $e) {
                $this->showForm($e->getMessage());
            }
            $email    = common_canonical_email($email);

			if ($email && !Validate::email($email, common_config('email', 'check_domain'))) {
                // TRANS: Form validation error displayed when trying to register without a valid e-mail address.
	            $this->clientError(_('Not a valid email address.'),404,'json');	
            } else if ($this->nicknameExists($nickname)) {
                // TRANS: Form validation error displayed when trying to register with an existing nickname.
	            $this->clientError(_('Nickname already in use. Try another one.'),404,'json');	
            } else if (!User::allowed_nickname($nickname)) {
                // TRANS: Form validation error displayed when trying to register with an invalid nickname.
	            $this->clientError(_('Not a valid nickname.'),404,'json');	
            } else if ($this->emailExists($email)) {
                // TRANS: Form validation error displayed when trying to register with an already registered e-mail address.
	            $this->clientError(_('Email address already exists.'),404,'json');	
            } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !common_valid_http_url($homepage)) {
                // TRANS: Form validation error displayed when trying to register with an invalid homepage URL.
	            $this->clientError(_('Homepage is not a valid URL.'),404,'json');	
                return;
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                // TRANS: Form validation error displayed when trying to register with a too long full name.
	            $this->clientError(_('Full name is too long (maximum 255 characters).'),404,'json');	
                return;
            } else if (Profile::bioTooLong($bio)) {
                // TRANS: Form validation error on registration page when providing too long a bio text.
                // TRANS: %d is the maximum number of characters for bio; used for plural.
	            $this->clientError(sprintf(_m('Bio is too long (maximum %d character).',
                                           'Bio is too long (maximum %d characters).',
                                           Profile::maxBio()),
                                           Profile::maxBio()),404,'json');	
                return;
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                // TRANS: Form validation error displayed when trying to register with a too long location.
	            $this->clientError(_('Location is too long (maximum 255 characters).'),404,'json');	
                return;
            } else if (strlen($password) < 6) {
                // TRANS: Form validation error displayed when trying to register with too short a password.
	            $this->clientError(_('Password must be 6 or more characters.'),404,'json');	
                return;
            } else if ($password != $confirm) {
                // TRANS: Form validation error displayed when trying to register with non-matching passwords.
	            $this->clientError(_('Passwords do not match.'),404,'json');	
            } else {
				
				// annoy spammers
				sleep(7);
				
				if ($user = User::register(array('nickname' => $nickname,
	                                                    'password' => $password,
	                                                    'email' => $email,
	                                                    'fullname' => $fullname,
	                                                    'homepage' => $homepage,
	                                                    'bio' => $bio,
	                                                    'location' => $location,
	                                                    'code' => $code))) {
	                if (!$user) {
	                    // TRANS: Form validation error displayed when trying to register with an invalid username or password.
			            $this->clientError(_('Invalid username or password.'),404,'json');	
	                    return;
	                }
	
	                Event::handle('EndRegistrationTry', array($this));
	
			        $this->initDocument('json');
			        $this->showJsonObjects($this->twitterUserArray($user->getProfile()));
			        $this->endDocument('json');
	
	            } else {
	                // TRANS: Form validation error displayed when trying to register with an invalid username or password.
	            	$this->clientError(_('Invalid username or password.'),404,'json');	
            	}	            	
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

}

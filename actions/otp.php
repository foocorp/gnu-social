<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Allow one-time password login
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Allow one-time password login
 *
 * This action will automatically log in the user identified by the user_id
 * parameter. A login_token record must be constructed beforehand, typically
 * by code where the user is already authenticated.
 *
 * @category  Login
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class OtpAction extends Action
{
    var $user;
    var $token;
    var $rememberme;
    var $returnto;
    var $lt;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_is_real_login()) {
            // TRANS: Client error displayed trying to use "one time password login" when already logged in.
            $this->clientError(_('Already logged in.'));
            return false;
        }

        $id = $this->trimmed('user_id');

        if (empty($id)) {
            // TRANS: Client error displayed trying to use "one time password login" without specifying a user.
            $this->clientError(_('No user ID specified.'));
            return false;
        }

        $this->user = User::getKV('id', $id);

        if (empty($this->user)) {
            // TRANS: Client error displayed trying to use "one time password login" without using an existing user.
            $this->clientError(_('No such user.'));
            return false;
        }

        $this->token = $this->trimmed('token');

        if (empty($this->token)) {
            // TRANS: Client error displayed trying to use "one time password login" without specifying a login token.
            $this->clientError(_('No login token specified.'));
            return false;
        }

        $this->lt = Login_token::getKV('user_id', $id);

        if (empty($this->lt)) {
            // TRANS: Client error displayed trying to use "one time password login" without requesting a login token.
            $this->clientError(_('No login token requested.'));
            return false;
        }

        if ($this->lt->token != $this->token) {
            // TRANS: Client error displayed trying to use "one time password login" while specifying an invalid login token.
            $this->clientError(_('Invalid login token specified.'));
            return false;
        }

        if ($this->lt->modified > time() + Login_token::TIMEOUT) {
            //token has expired
            //delete the token as it is useless
            $this->lt->delete();
            $this->lt = null;
            // TRANS: Client error displayed trying to use "one time password login" while specifying an expired login token.
            $this->clientError(_('Login token expired.'));
            return false;
        }

        $this->rememberme = $this->boolean('rememberme');
        $this->returnto = $this->trimmed('returnto');

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        // success!
        if (!common_set_user($this->user)) {
            // TRANS: Server error displayed when a user object could not be created trying to login using "one time password login".
            $this->serverError(_('Error setting user. You are probably not authorized.'));
            return;
        }

        // We're now logged in; disable the lt

        $this->lt->delete();
        $this->lt = null;

        common_real_login(true);

        if ($this->rememberme) {
            common_rememberme($this->user);
        }

        if (!empty($this->returnto)) {
            $url = $this->returnto;
            // We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $this->user->nickname));
        }

        common_redirect($url, 303);
    }
}

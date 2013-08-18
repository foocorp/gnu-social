<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to use crypt() for user password hashes
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2012 StatusNet, Inc.
 * @copyright 2012 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to use crypt() for user password hashes
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2012 StatusNet, Inc.
 * @copyright 2012 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AuthCryptPlugin extends AuthenticationPlugin
{
    public $hash;	// defaults to SHA512, i.e. '$6$', in onInitializePlugin()
    public $hostile;	// if true, password login means change password to crypt() hash
    public $overwrite;	// if true, password change means always store crypt() hash

    /*
     * FUNCTIONALITY
     */

    function checkPassword($username, $password) {
        $user = User::getKV('nickname', common_canonical_nickname($username));

        // crypt cuts the second parameter to its appropriate length based on hash scheme
        if (!empty($user) && $user->password === crypt($password, $user->password)) {
            return $user;
        }
        // if we're hostile, check the password against old-style hashing
        if (!empty($user) && $this->hostile && $user->password === md5($password . $user->id)) {
            // update password hash entry to crypt() compatible
            $this->changePassword($username, $password, $password);
            return $user;
        }

        return false;
    }

    // $oldpassword is already verified when calling this function... shouldn't this be private?!
    function changePassword($username, $oldpassword, $newpassword) {
        if (!$this->password_changeable) {
            return false;
        }

        $user = User::getKV('nickname', $username);
        if (empty($user)) {
            return false;
        }
        $original = clone($user);

        // a new, unique salt per new record stored... but common_good_rand should be more diverse than hexdec
        $user->password = crypt($newpassword, $this->hash . common_good_rand(CRYPT_SALT_LENGTH));

        return (true === $user->validate() && $user->update($original));
    }

    /*
     * EVENTS
     */

    function onInitializePlugin() {
        $this->provider_name = 'crypt';	// we don't actually use the provider_name

        if (!isset($this->hash)) {
            $this->hash = '$6$';	// SHA512 supported on all systems since PHP 5.3.2
        }
        if (!isset($this->hostile)) {
            $this->hostile = false;	// overwrite old style password hashes?
        }
        if (!isset($this->overwrite)) {
            $this->overwrite = true;	// overwrite old style password hashes?
        }
    }

    function onStartChangePassword($user, $oldpassword, $newpassword) {
		if (!$this->checkPassword($user->nickname, $oldpassword)) {
            // if we ARE in overwrite mode, test password with common_check_user
            if (!$this->overwrite || !common_check_user($user->nickname, $oldpassword)) {
                // either we're not in overwrite mode, or the password was incorrect
                return !$this->authoritative;
            }
            // oldpassword was apparently ok
        }
        $changed = $this->changePassword($user->nickname, $oldpassword, $newpassword);

		return (!$changed && empty($this->authoritative));
    }

    function onStartCheckPassword($nickname, $password, &$authenticatedUser) {
        $authenticatedUser = $this->checkPassword($nickname, $password);
		return (empty($authenticatedUser) && empty($this->authoritative));
    }

    function onCheckSchema() {
        // we only use the User database, so default AuthenticationPlugin stuff can be ignored
        return true;
    }

    function onUserDeleteRelated($user, &$tables) {
        // not using User_username table, so no need to add it here.
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'AuthCrypt',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://status.net/wiki/Plugin:AuthCrypt',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Authentication and password hashing with crypt()'));
        return true;
    }
}

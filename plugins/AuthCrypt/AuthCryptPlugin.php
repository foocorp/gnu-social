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
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2012 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class AuthCryptPlugin extends AuthenticationPlugin
{
    protected $hash         = '$6$';    // defaults to SHA512, i.e. '$6$', in onInitializePlugin()
    protected $statusnet    = true;     // if true, also check StatusNet style password hash
    protected $overwrite    = true;     // if true, password change means overwrite with crypt()

    public $provider_name   = 'crypt';  // not actually used

    /*
     * FUNCTIONALITY
     */

    function checkPassword($username, $password)
    {
        $user = User::getKV('nickname', $username);
        if (!($user instanceof User)) {
            return false;
        }

        // crypt understands what the salt part of $user->password is
        if ($user->password === crypt($password, $user->password)) {
            return $user;
        }

        // If we check StatusNet hash, for backwards compatibility and migration
        if ($this->statusnet && $user->password === md5($password . $user->id)) {
            // and update password hash entry to crypt() compatible
            if ($this->overwrite) {
                $this->changePassword($user->nickname, null, $password);
            }
            return $user;
        }

        return false;
    }

    protected function cryptSalt($len=CRYPT_SALT_LENGTH)
    {
        $chars = "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $salt  = '';

        for ($i=0; $i<$len; $i++) {
            $salt .= $chars{mt_rand(0, strlen($chars)-1)};
        }

        return $salt;
    }

    // $oldpassword is already verified when calling this function... shouldn't this be private?!
    function changePassword($username, $oldpassword, $newpassword)
    {
        if (!$this->password_changeable) {
            return false;
        }

        $user = User::getKV('nickname', $username);
        if (empty($user)) {
            return false;
        }
        $original = clone($user);

        $user->password = $this->hashPassword($newpassword, $user->getProfile());

        return (true === $user->validate() && $user->update($original));
    }

    public function hashPassword($password, Profile $profile=null)
    {
        // A new, unique salt per new record stored...
        return crypt($password, $this->hash . self::cryptSalt());
    }

    /*
     * EVENTS
     */

    public function onStartChangePassword($user, $oldpassword, $newpassword)
    {
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

    public function onStartCheckPassword($nickname, $password, &$authenticatedUser)
    {
        $authenticatedUser = $this->checkPassword($nickname, $password);
        // if we failed, only return false to stop plugin execution if we're authoritative
        return (!($authenticatedUser instanceof User) && empty($this->authoritative));
    }

    public function onStartHashPassword(&$hashed, $password, Profile $profile=null)
    {
        $hashed = $this->hashPassword($password, $profile);
        return false;
    }

    public function onCheckSchema()
    {
        // we only use the User database, so default AuthenticationPlugin stuff can be ignored
        return true;
    }

    public function onUserDeleteRelated($user, &$tables)
    {
        // not using User_username table, so no need to add it here.
        return true;
    }

    public function onPluginVersion(&$versions)
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

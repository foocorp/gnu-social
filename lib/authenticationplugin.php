<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do authentication and/or authorization
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Superclass for plugins that do authentication
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
abstract class AuthenticationPlugin extends Plugin
{
    //is this plugin authoritative for authentication?
    public $authoritative = false;

    //should accounts be automatically created after a successful login attempt?
    public $autoregistration = false;

    //can the user change their email address
    public $password_changeable=true;

    //unique name for this authentication provider
    public $provider_name;

    //------------Auth plugin should implement some (or all) of these methods------------\\
    /**
    * Check if a nickname/password combination is valid
    * @param username
    * @param password
    * @return boolean true if the credentials are valid, false if they are invalid.
    */
    function checkPassword($username, $password)
    {
        return false;
    }

    /**
    * Automatically register a user when they attempt to login with valid credentials.
    * User::register($data) is a very useful method for this implementation
    * @param username username (that is used to login and find the user in the authentication provider) of the user to be registered
    * @param nickname nickname of the user in the SN system. If nickname is null, then set nickname = username
    * @return mixed instance of User, or false (if user couldn't be created)
    */
    function autoRegister($username, $nickname = null)
    {
        if(is_null($nickname)){
            $nickname = $username;
        }
        $registration_data = array();
        $registration_data['nickname'] = $nickname;
        return User::register($registration_data);
    }

    /**
    * Change a user's password
    * The old password has been verified to be valid by this plugin before this call is made
    * @param username
    * @param oldpassword
    * @param newpassword
    * @return boolean true if the password was changed, false if password changing failed for some reason
    */
    function changePassword($username,$oldpassword,$newpassword)
    {
        return false;
    }

    /**
    * Given a username, suggest what the nickname should be
    * Used during autoregistration
    * Useful if your usernames are ugly, and you want to suggest
    * nice looking nicknames when users initially sign on
    * All nicknames returned by this function should be valid
    *  implementations may want to use common_nicknamize() to ensure validity
    * @param username
    * @return string nickname
    */
    function suggestNicknameForUsername($username)
    {
        return common_nicknamize($username);
    }

    //------------Below are the methods that connect StatusNet to the implementing Auth plugin------------\\
    function onInitializePlugin(){
        if(!isset($this->provider_name)){
            throw new Exception("must specify a provider_name for this authentication provider");
        }
    }

    /**
    * Internal AutoRegister event handler
    * @param nickname
    * @param provider_name
    * @param user - the newly registered user
    */
    function onAutoRegister($nickname, $provider_name, &$user)
    {
        if($provider_name == $this->provider_name && $this->autoregistration){
            $suggested_nickname = $this->suggestNicknameForUsername($nickname);
            $test_user = User::getKV('nickname', $suggested_nickname);
            if($test_user) {
                //someone already exists with the suggested nickname, so used the passed nickname
                $suggested_nickname = common_nicknamize($nickname);
            }
            $test_user = User::getKV('nickname', $suggested_nickname);
            if($test_user) {
                //someone already exists with the suggested nickname
                //not much else we can do
            }else{
                $user = $this->autoRegister($nickname, $suggested_nickname);
                if ($user instanceof User) {
                    User_username::register($user,$nickname,$this->provider_name);
                    return false;
                }
            }
        }
    }

    function onStartCheckPassword($nickname, $password, &$authenticatedUser){
        //map the nickname to a username
        $user_username = new User_username();
        $user_username->username=$nickname;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $authenticated = $this->checkPassword($user_username->username, $password);
            if($authenticated){
                $authenticatedUser = User::getKV('id', $user_username->user_id);
                return false;
            }
        }else{
            //$nickname is the username used to login
            //$suggested_nickname is the nickname the auth provider suggests for that username
            $suggested_nickname = $this->suggestNicknameForUsername($nickname);
            $user = User::getKV('nickname', $suggested_nickname);
            if($user){
                //make sure this user isn't claimed
                $user_username = new User_username();
                $user_username->user_id=$user->id;
                $we_can_handle = false;
                if($user_username->find()){
                    //either this provider, or another one, has already claimed this user
                    //so we cannot. Let another plugin try.
                    return;
                }else{
                    //no other provider claims this user, so it's safe for us to handle it
                    $authenticated = $this->checkPassword($nickname, $password);
                    if($authenticated){
                        $authenticatedUser = $user;
                        User_username::register($authenticatedUser,$nickname,$this->provider_name);
                        return false;
                    }
                }
            }else{
                $authenticated = $this->checkPassword($nickname, $password);
                if($authenticated){
                    if(! Event::handle('AutoRegister', array($nickname, $this->provider_name, &$authenticatedUser))){
                        //unlike most Event::handle lines of code, this one has a ! (not)
                        //we want to do this if the event *was* handled - this isn't a "default" implementation
                        //like most code of this form.
                        if($authenticatedUser){
                            return false;
                        }
                    }
                }
            }
        }
        if($this->authoritative){
            return false;
        }else{
            //we're not authoritative, so let other handlers try
            return;
        }
    }

    function onStartChangePassword(Profile $target ,$oldpassword, $newpassword)
    {
        if($this->password_changeable){
            $user_username = new User_username();
            $user_username->user_id = $target->getID();
            $user_username->provider_name=$this->provider_name;
            if ($user_username->find(true)) {
                $authenticated = $this->checkPassword($user_username->username, $oldpassword);
                if($authenticated){
                    $result = $this->changePassword($user_username->username,$oldpassword,$newpassword);
                    if($result){
                        //stop handling of other handlers, because what was requested was done
                        return false;
                    }else{
                        // TRANS: Exception thrown when a password change fails.
                        throw new Exception(_('Password changing failed.'));
                    }
                }else{
                    if($this->authoritative){
                        //since we're authoritative, no other plugin could do this
                        // TRANS: Exception thrown when a password change fails.
                        throw new Exception(_('Password changing failed.'));
                    }else{
                        //let another handler try
                        return null;
                    }
                }
            }
        }else{
            if($this->authoritative){
                //since we're authoritative, no other plugin could do this
                // TRANS: Exception thrown when a password change attempt fails because it is not allowed.
                throw new Exception(_('Password changing is not allowed.'));
            }
        }
    }

    function onStartAccountSettingsPasswordMenuItem($widget)
    {
        if($this->authoritative && !$this->password_changeable){
            //since we're authoritative, no other plugin could change passwords, so do not render the menu item
            return false;
        }
    }

    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('user_username', User_username::schemaDef());
        return true;
    }

    function onUserDeleteRelated($user, &$tables)
    {
        $tables[] = 'User_username';
        return true;
    }
}

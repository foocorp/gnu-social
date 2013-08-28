<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable LDAP Authentication
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class LdapAuthenticationPlugin extends AuthenticationPlugin
{
    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->attributes['nickname'])){
            // TRANS: Exception thrown when initialising the LDAP Auth plugin fails because of an incorrect configuration.
            throw new Exception(_m('You must specify a nickname attribute.'));
        }
        if($this->password_changeable && (! isset($this->attributes['password']) || !isset($this->password_encoding))){
            // TRANS: Exception thrown when initialising the LDAP Auth plugin fails because of an incorrect configuration.
            throw new Exception(_m('If password_changeable is set, the password attribute and password_encoding must also be specified.'));
        }
        $this->ldapCommon = new LdapCommon(get_object_vars($this));
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'LdapCommon':
            require_once(INSTALLDIR.'/plugins/LdapCommon/LdapCommon.php');
            return false;
        }

        return parent::onAutoload($cls);
    }

    function onEndShowPageNotice($action)
    {
        $name = $action->trimmed('action');
        $instr = false;

        switch ($name)
        {
         case 'register':
            if($this->autoregistration) {
                // TRANS: Instructions for LDAP authentication.
                $instr = _m('Do you have an LDAP account? Use your standard username and password.');
            }
            break;
         case 'login':
            // TRANS: Instructions for LDAP authentication.
            $instr = _m('Do you have an LDAP account? Use your standard username and password.');
            break;
         default:
            return true;
        }

        if($instr) {
            $output = common_markup_to_html($instr);
            $action->raw($output);
        }
        return true;
    }

    //---interface implementation---//

    function checkPassword($username, $password)
    {
        return $this->ldapCommon->checkPassword($username,$password);
    }

    function autoRegister($username, $nickname)
    {
        if(is_null($nickname)){
            $nickname = $username;
        }
        $entry = $this->ldapCommon->get_user($username,$this->attributes);
        if($entry){
            $registration_data = array();
            foreach($this->attributes as $sn_attribute=>$ldap_attribute){
                //ldap won't let us read a user's password,
                //and we're going to set the password to a random string later anyways,
                //so don't bother trying to read it.
                if($sn_attribute != 'password'){
                    $registration_data[$sn_attribute]=$entry->getValue($ldap_attribute,'single');
                }
            }
            if(isset($registration_data['email']) && !empty($registration_data['email'])){
                $registration_data['email_confirmed']=true;
            }
            $registration_data['nickname'] = $nickname;
            //set the database saved password to a random string.
            $registration_data['password']=common_good_rand(16);
            return User::register($registration_data);
        }else{
            //user isn't in ldap, so we cannot register him
            return false;
        }
    }

    function changePassword($username,$oldpassword,$newpassword)
    {
        return $this->ldapCommon->changePassword($username,$oldpassword,$newpassword);
    }

    function suggestNicknameForUsername($username)
    {
        $entry = $this->ldapCommon->get_user($username, $this->attributes);
        if(!$entry){
            //this really shouldn't happen
            $nickname = $username;
        }else{
            $nickname = $entry->getValue($this->attributes['nickname'],'single');
            if(!$nickname){
                $nickname = $username;
            }
        }
        return common_nicknamize($nickname);
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'LDAP Authentication',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:LdapAuthentication',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The LDAP Authentication plugin allows for StatusNet to handle authentication through LDAP.'));
        return true;
    }
}

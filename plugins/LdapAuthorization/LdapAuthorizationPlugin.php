<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable LDAP Authorization
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

class LdapAuthorizationPlugin extends AuthorizationPlugin
{
    public $roles_to_groups = array();
    public $login_group = null;

    function onInitializePlugin(){
        if(!isset($this->provider_name)){
            // TRANS: Exception thrown when initialising the LDAP Auth plugin fails because of an incorrect configuration.
            throw new Exception(_m('provider_name must be set. Use the provider_name from the LDAP Authentication plugin.'));
        }
        if(!isset($this->uniqueMember_attribute)){
            // TRANS: Exception thrown when initialising the LDAP Auth plugin fails because of an incorrect configuration.
            throw new Exception(_m('uniqueMember_attribute must be set.'));
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

    //---interface implementation---//
    function loginAllowed($user) {
        $user_username = new User_username();
        $user_username->user_id=$user->id;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $entry = $this->ldapCommon->get_user($user_username->username);
            if($entry){
                if(isset($this->login_group)){
                    if(is_array($this->login_group)){
                        foreach($this->login_group as $group){
                            if($this->ldapCommon->is_dn_member_of_group($entry->dn(),$group)){
                                return true;
                            }
                        }
                    }else{
                        if($this->ldapCommon->is_dn_member_of_group($entry->dn(),$this->login_group)){
                            return true;
                        }
                    }
                    return null;
                }else{
                    //if a user exists, we can assume he's allowed to login
                    return true;
                }
            }else{
                return null;
            }
        }else{
            return null;
        }
    }

    function hasRole($profile, $name) {
        $user_username = new User_username();
        $user_username->user_id=$profile->id;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $entry = $this->ldapCommon->get_user($user_username->username);
            if($entry){
                if(isset($this->roles_to_groups[$name])){
                    if(is_array($this->roles_to_groups[$name])){
                        foreach($this->roles_to_groups[$name] as $group){
                            if($this->ldapCommon->is_dn_member_of_group($entry->dn(),$group)){
                                return true;
                            }
                        }
                    }else{
                        if($this->ldapCommon->is_dn_member_of_group($entry->dn(),$this->roles_to_groups[$name])){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'LDAP Authorization',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:LdapAuthorization',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The LDAP Authorization plugin allows for StatusNet to handle authorization through LDAP.'));
        return true;
    }
}

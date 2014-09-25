<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility class of LDAP functions
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

// We bundle the Net/LDAP2 library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib');

class LdapCommon
{
    protected static $ldap_connections = array();
    public $host=null;
    public $port=null;
    public $version=null;
    public $starttls=null;
    public $binddn=null;
    public $bindpw=null;
    public $basedn=null;
    public $options=null;
    public $filter=null;
    public $scope=null;
    public $uniqueMember_attribute = null;
    public $attributes=array();
    public $password_encoding=null;

    public function __construct($config)
    {
        Event::addHandler('Autoload',array($this,'onAutoload'));
        foreach($config as $key=>$value) {
            $this->$key = $value;
        }
        $this->ldap_config = $this->get_ldap_config();

        if(!isset($this->host)){
            // TRANS: Exception thrown when initialising the LDAP Common plugin fails because of an incorrect configuration.
            throw new Exception(_m('A host must be specified.'));
        }
        if(!isset($this->basedn)){
            // TRANS: Exception thrown when initialising the LDAP Common plugin fails because of an incorrect configuration.
            throw new Exception(_m('"basedn" must be specified.'));
        }
        if(!isset($this->attributes['username'])){
            // TRANS: Exception thrown when initialising the LDAP Common plugin fails because of an incorrect configuration.
            throw new Exception(_m('The username attribute must be set.'));
        }
    }

    function onAutoload($cls)
    {
        // we've added an extra include-path in the beginning of this file
        switch ($cls)
        {
         case 'MemcacheSchemaCache':
            require_once(INSTALLDIR.'/plugins/LdapCommon/MemcacheSchemaCache.php');
            return false;
         case 'Net_LDAP2':
            require_once 'Net/LDAP2.php';
            return false;
         case 'Net_LDAP2_Filter':
            require_once 'Net/LDAP2/Filter.php';
            return false;
         case 'Net_LDAP2_Filter':
            require_once 'Net/LDAP2/Filter.php';
            return false;
         case 'Net_LDAP2_Entry':
            require_once 'Net/LDAP2/Entry.php';
            return false;
        }

        return true;
    }

    function get_ldap_config(){
        $config = array();
        $keys = array('host','port','version','starttls','binddn','bindpw','basedn','options','filter','scope');
        foreach($keys as $key){
            $value = $this->$key;
            if($value!==null){
                $config[$key]=$value;
            }
        }
        return $config;
    }

    function get_ldap_connection($config = null){
        if($config == null) {
            $config = $this->ldap_config;
        }
        $config_id = crc32(serialize($config));
        if(array_key_exists($config_id,self::$ldap_connections)) {
            $ldap = self::$ldap_connections[$config_id];
        } else {
            //cannot use Net_LDAP2::connect() as StatusNet uses
            //PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handleError');
            //PEAR handling can be overridden on instance objects, so we do that.
            $ldap = new Net_LDAP2($config);
            $ldap->setErrorHandling(PEAR_ERROR_RETURN);
            $err=$ldap->bind();
            if (Net_LDAP2::isError($err)) {
                // if we were called with a config, assume caller will handle
                // incorrect username/password (LDAP_INVALID_CREDENTIALS)
                if (isset($config) && $err->getCode() == 0x31) {
                    // TRANS: Exception thrown in the LDAP Common plugin when LDAP server is not available.
                    // TRANS: %s is the error message.
                    throw new LdapInvalidCredentialsException(sprintf(_m('Could not connect to LDAP server: %s'),$err->getMessage()));
                }
                // TRANS: Exception thrown in the LDAP Common plugin when LDAP server is not available.
                // TRANS: %s is the error message.
                throw new Exception(sprintf(_m('Could not connect to LDAP server: %s.'),$err->getMessage()));
            }
            $c = Cache::instance();
            if (!empty($c)) {
                $cacheObj = new MemcacheSchemaCache(
                    array('c'=>$c,
                       'cacheKey' => Cache::key('ldap_schema:' . $config_id)));
                $ldap->registerSchemaCache($cacheObj);
            }
            self::$ldap_connections[$config_id] = $ldap;
        }
        return $ldap;
    }

    function checkPassword($username, $password)
    {
        $entry = $this->get_user($username,array('dn' => 'dn'));
        if(!$entry){
            return false;
        }else{
            if(empty($password)) {
                //NET_LDAP2 will do an anonymous bind if bindpw is not set / empty string
                //which causes all login attempts that involve a blank password to appear
                //to succeed. Which is obviously not good.
                return false;
            }
            $config = $this->get_ldap_config();
            $config['binddn']=$entry->dn();
            $config['bindpw']=$password;
            try {
                $this->get_ldap_connection($config);
            } catch (LdapInvalidCredentialsException $e) {
                return false;
            }
            return true;
        }
    }

    function changePassword($username,$oldpassword,$newpassword)
    {
        if(! isset($this->attributes['password']) || !isset($this->password_encoding)){
            //throw new Exception(_m('Sorry, changing LDAP passwords is not supported at this time.'));
            return false;
        }
        $entry = $this->get_user($username,array('dn' => 'dn'));
        if(!$entry){
            return false;
        }else{
            $config = $this->get_ldap_config();
            $config['binddn']=$entry->dn();
            $config['bindpw']=$oldpassword;
            try {
                $ldap = $this->get_ldap_connection($config);

                $entry = $this->get_user($username,array(),$ldap);

                $newCryptedPassword = $this->hashPassword($newpassword, $this->password_encoding);
                if ($newCryptedPassword===false) {
                    return false;
                }
                if($this->password_encoding=='ad') {
                    //TODO I believe this code will work once this bug is fixed: http://pear.php.net/bugs/bug.php?id=16796
                    $oldCryptedPassword = $this->hashPassword($oldpassword, $this->password_encoding);
                    $entry->delete( array($this->attributes['password'] => $oldCryptedPassword ));
                }
                $entry->replace( array($this->attributes['password'] => $newCryptedPassword ), true);
                if( Net_LDAP2::isError($entry->upate()) ) {
                    return false;
                }
                return true;
            } catch (LdapInvalidCredentialsException $e) {
                return false;
            }
        }

        return false;
    }

    function is_dn_member_of_group($userDn, $groupDn)
    {
        $ldap = $this->get_ldap_connection();
        $link = $ldap->getLink();
        $r = @ldap_compare($link, $groupDn, $this->uniqueMember_attribute, $userDn);
        if ($r === true){
            return true;
        }else if($r === false){
            return false;
        }else{
            common_log(LOG_ERR, "LDAP error determining if userDn=$userDn is a member of groupDn=$groupDn using uniqueMember_attribute=$this->uniqueMember_attribute error: ".ldap_error($link));
            return false;
        }
    }

    /**
     * get an LDAP entry for a user with a given username
     *
     * @param string $username
     * $param array $attributes LDAP attributes to retrieve
     * @return string DN
     */
    function get_user($username,$attributes=array()){
        $ldap = $this->get_ldap_connection();
        $filter = Net_LDAP2_Filter::create($this->attributes['username'], 'equals',  $username);
        $options = array(
            'attributes' => $attributes
        );
        $search = $ldap->search(null,$filter,$options);

        if (PEAR::isError($search)) {
            common_log(LOG_WARNING, 'Error while getting DN for user: '.$search->getMessage());
            return false;
        }

        if($search->count()==0){
            return false;
        }else if($search->count()==1){
            $entry = $search->shiftEntry();
            return $entry;
        }else{
            common_log(LOG_WARNING, 'Found ' . $search->count() . ' ldap user with the username: ' . $username);
            return false;
        }
    }

    /**
     * Code originaly from the phpLDAPadmin development team
     * http://phpldapadmin.sourceforge.net/
     *
     * Hashes a password and returns the hash based on the specified enc_type.
     *
     * @param string $passwordClear The password to hash in clear text.
     * @param string $encodageType Standard LDAP encryption type which must be one of
     *        crypt, ext_des, md5crypt, blowfish, md5, sha, smd5, ssha, or clear.
     * @return string The hashed password.
     *
     */
    function hashPassword( $passwordClear, $encodageType )
    {
        $encodageType = strtolower( $encodageType );
        switch( $encodageType ) {
            case 'crypt':
                $cryptedPassword = '{CRYPT}' . crypt($passwordClear,$this->randomSalt(2));
                break;

            case 'ext_des':
                // extended des crypt. see OpenBSD crypt man page.
                if ( ! defined( 'CRYPT_EXT_DES' ) || CRYPT_EXT_DES == 0 ) {return FALSE;} //Your system crypt library does not support extended DES encryption.
                $cryptedPassword = '{CRYPT}' . crypt( $passwordClear, '_' . $this->randomSalt(8) );
                break;

            case 'md5crypt':
                if( ! defined( 'CRYPT_MD5' ) || CRYPT_MD5 == 0 ) {return FALSE;} //Your system crypt library does not support md5crypt encryption.
                $cryptedPassword = '{CRYPT}' . crypt( $passwordClear , '$1$' . $this->randomSalt(9) );
                break;

            case 'blowfish':
                if( ! defined( 'CRYPT_BLOWFISH' ) || CRYPT_BLOWFISH == 0 ) {return FALSE;} //Your system crypt library does not support blowfish encryption.
                $cryptedPassword = '{CRYPT}' . crypt( $passwordClear , '$2a$12$' . $this->randomSalt(13) ); // hardcoded to second blowfish version and set number of rounds
                break;

            case 'md5':
                $cryptedPassword = '{MD5}' . base64_encode( pack( 'H*' , md5( $passwordClear) ) );
                break;

            case 'sha':
                if( function_exists('sha1') ) {
                    // use php 4.3.0+ sha1 function, if it is available.
                    $cryptedPassword = '{SHA}' . base64_encode( pack( 'H*' , sha1( $passwordClear) ) );
                } elseif( function_exists( 'mhash' ) ) {
                    $cryptedPassword = '{SHA}' . base64_encode( mhash( MHASH_SHA1, $passwordClear) );
                } else {
                    return FALSE; //Your PHP install does not have the mhash() function. Cannot do SHA hashes.
                }
                break;

            case 'ssha':
                if( function_exists( 'mhash' ) && function_exists( 'mhash_keygen_s2k' ) ) {
                    mt_srand( (double) microtime() * 1000000 );
                    $salt = mhash_keygen_s2k( MHASH_SHA1, $passwordClear, substr( pack( "h*", md5( mt_rand() ) ), 0, 8 ), 4 );
                    $cryptedPassword = "{SSHA}".base64_encode( mhash( MHASH_SHA1, $passwordClear.$salt ).$salt );
                } else {
                    return FALSE; //Your PHP install does not have the mhash() function. Cannot do SHA hashes.
                }
                break;

            case 'smd5':
                if( function_exists( 'mhash' ) && function_exists( 'mhash_keygen_s2k' ) ) {
                    mt_srand( (double) microtime() * 1000000 );
                    $salt = mhash_keygen_s2k( MHASH_MD5, $passwordClear, substr( pack( "h*", md5( mt_rand() ) ), 0, 8 ), 4 );
                    $cryptedPassword = "{SMD5}".base64_encode( mhash( MHASH_MD5, $passwordClear.$salt ).$salt );
                } else {
                    return FALSE; //Your PHP install does not have the mhash() function. Cannot do SHA hashes.
                }
                break;

            case 'ad':
                $cryptedPassword = '';
                $passwordClear = "\"" . $passwordClear . "\"";
                $len = strlen($passwordClear);
                for ($i = 0; $i < $len; $i++) {
                    $cryptedPassword .= "{$passwordClear{$i}}\000";
                }

            case 'clear':
            default:
                $cryptedPassword = $passwordClear;
        }

        return $cryptedPassword;
    }

    /**
     * Code originaly from the phpLDAPadmin development team
     * http://phpldapadmin.sourceforge.net/
     *
     * Used to generate a random salt for crypt-style passwords. Salt strings are used
     * to make pre-built hash cracking dictionaries difficult to use as the hash algorithm uses
     * not only the user's password but also a randomly generated string. The string is
     * stored as the first N characters of the hash for reference of hashing algorithms later.
     *
     * --- added 20021125 by bayu irawan <bayuir@divnet.telkom.co.id> ---
     * --- ammended 20030625 by S C Rigler <srigler@houston.rr.com> ---
     *
     * @param int $length The length of the salt string to generate.
     * @return string The generated salt string.
     */
    function randomSalt( $length )
    {
        $possible = '0123456789'.
            'abcdefghijklmnopqrstuvwxyz'.
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.
            './';
        $str = "";
        mt_srand((double)microtime() * 1000000);

        while( strlen( $str ) < $length )
            $str .= substr( $possible, ( rand() % strlen( $possible ) ), 1 );

        return $str;
    }
}

class LdapInvalidCredentialsException extends Exception
{
}

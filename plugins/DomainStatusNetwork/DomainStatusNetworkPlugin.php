<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * One status_network per email domain
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

$_dir = dirname(__FILE__);

require_once $_dir . '/extlib/effectiveTLDs.inc.php';
require_once $_dir . '/extlib/regDomain.inc.php';

/**
 * Tools to map one status_network to one email domain in a multi-site
 * installation.
 *
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DomainStatusNetworkPlugin extends Plugin
{
    static $_thetree = null;

    function initialize()
    {
        // For various reasons this gets squished

        global $tldTree;

        if (empty($tldTree)) {
            if (!empty(self::$_thetree)) {
                $tldTree = self::$_thetree;
            }
        }

        $nickname = StatusNet::currentSite();

        if (empty($nickname)) {
            $this->log(LOG_WARNING, "No current site");
            return;
        }

        try {
            $sn = Status_network::getKV('nickname', $nickname);
        } catch (Exception $e) {
            $this->log(LOG_ERR, $e->getMessage());
            return;
        }

        $tags = $sn->getTags();

        foreach ($tags as $tag) {
            if (strncmp($tag, 'domain=', 7) == 0) {
                $domain = substr($tag, 7);
                $this->log(LOG_INFO, "Setting email domain to {$domain}");
                common_config_append('email', 'whitelist', $domain);
            }
        }
    }

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'GlobalregisterAction':
        case 'GloballoginAction':
        case 'GlobalrecoverAction':
            include_once $dir . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'DomainStatusNetworkInstaller':
        case 'GlobalApiAction':
        case 'FreeEmail':
            include_once $dir . '/lib/' . strtolower($cls) . '.php';
            return false;
        default:
            return true;
        }
    }

    static function toDomain($raw)
    {
        $parts = explode('@', $raw);

        if (count($parts) == 1) {
            $domain = $parts[0];
        } else {
            $domain = $parts[1];
        }

        $domain = strtolower(trim($domain));

        return $domain;
    }

    static function registeredDomain($domain)
    {
        return getRegisteredDomain($domain);
    }

    static function nicknameAvailable($nickname)
    {
        $sn = Status_network::getKV('nickname', $nickname);
        if (!empty($sn)) {
            return false;
        }
        $usn = Unavailable_status_network::getKV('nickname', $nickname);
        if (!empty($usn)) {
            return false;
        }
        return true;
    }

    function onRouterInitialized($m)
    {
        if (common_config('globalapi', 'enabled')) {
            foreach (array('register', 'login', 'recover') as $method) {
                $m->connect('api/statusnet/global/'.$method,
                            array('action' => 'global'.$method));
            }
        }
        return true;
    }

    function onLoginAction($action, &$login) {
        $this->debug($action);
        if (in_array($action, array('globalregister', 'globallogin', 'globalrecover'))) {
            $login = true;
            return false;
        }
        return true;
    }

    static function nicknameForDomain($domain)
    {
        $registered = self::registeredDomain($domain);

        $parts = explode('.', $registered);

        $base = $parts[0];

        if (self::nicknameAvailable($base)) {
            return $base;
        }

        $domainish = str_replace('.', '-', $registered);

        if (self::nicknameAvailable($domainish)) {
            return $domainish;
        }

        $i = 1;

        // We don't need to keep doing this forever

        while ($i < 1024) {
            $candidate = $domainish.'-'.$i;
            if (self::nicknameAvailable($candidate)) {
                return $candidate;
            }
            $i++;
        }

        return null;
    }

    static function siteForDomain($domain)
    {
        $snt = Status_network_tag::withTag('domain='.$domain);

        while ($snt->fetch()) {
            $sn = Status_network::getKV('site_id', $snt->site_id);
            if (!empty($sn)) {
                return $sn;
            }
        }
        return null;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'DomainStatusNetwork',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:DomainStatusNetwork',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('A plugin that maps a single status_network to an email domain.'));
        return true;
    }

    static function userExists($email)
    {
        $domain = self::toDomain($email);

        $sn = self::siteForDomain($domain);

        if (empty($sn)) {
            return false;
        }

        StatusNet::switchSite($sn->nickname);

        $user = User::getKV('email', $email);

        return !empty($user);
    }

    static function registerEmail($email)
    {
        $domain = self::toDomain($email);

        if (FreeEmail::isFree($domain)) {
            throw new ClientException(_("Use your work email."));
        }

        $sn = self::siteForDomain($domain);

        if (empty($sn)) {
            $installer = new DomainStatusNetworkInstaller($domain);

            // Do the thing
            $installer->main();

            $sn = $installer->getStatusNetwork();

            $config = $installer->getConfig();

            Status_network::$wildcard = $config['WILDCARD'];
        }

        StatusNet::switchSite($sn->nickname);

        $confirm = EmailRegistrationPlugin::registerEmail($email);

        return $confirm;
    }

    static function login($email, $password)
    {
        $domain = self::toDomain($email);

        $sn = self::siteForDomain($domain);

        if (empty($sn)) {
            throw new ClientException(_("No such site."));
        }

        StatusNet::switchSite($sn->nickname);

        $user = common_check_user($email, $password);

        if (empty($user)) {
            // TRANS: Form validation error displayed when trying to log in with incorrect credentials.
            throw new ClientException(_('Incorrect username or password.'));
        }

        $loginToken = Login_token::makeNew($user);

        if (empty($loginToken)) {
            throw new ServerException(sprintf(_('Could not create new login token for user %s'), $user->nickname));
        }

        $url = common_local_url('otp', array('user_id' => $loginToken->user_id,
                                             'token' => $loginToken->token));

        if (empty($url)) {
            throw new ServerException(sprintf(_('Could not create new OTP URL for user %s'), $user->nickname));
        }

        return $url;
    }

    static function recoverPassword($email)
    {
        $domain = self::toDomain($email);

        $sn = self::siteForDomain($domain);

        if (empty($sn)) {
            throw new NoSuchUserException(array('email' => $email));
        }

        StatusNet::switchSite($sn->nickname);

        $user = User::getKV('email', $email);
        
        if (empty($user)) {
            throw new ClientException(_('No such user.'));
        }
    }
}

// The way addPlugin() works, this global variable gets disappeared.
// So, we re-appear it.

DomainStatusNetworkPlugin::$_thetree = $tldTree;

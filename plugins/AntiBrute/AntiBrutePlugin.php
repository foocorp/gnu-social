<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class AntiBrutePlugin extends Plugin {
    protected $failed_attempts = 0;
    protected $unauthed_user = null;
    protected $client_ip = null;

    const FAILED_LOGIN_IP_SECTION = 'failed_login_ip';

    public function onStartCheckPassword($nickname, $password, &$authenticatedUser)
    {
        if (common_is_email($nickname)) {
            $this->unauthed_user = User::getKV('email', common_canonical_email($nickname));
        } else {
            $this->unauthed_user = User::getKV('nickname', Nickname::normalize($nickname));
        }

        if (!$this->unauthed_user instanceof User) {
            // Unknown username continue processing StartCheckPassword (maybe uninitialized LDAP user etc?)
            return true;
        }

        // This probably needs some work. For example with IPv6 you can easily generate new IPs...
        $client_ip = common_client_ip();
        $this->client_ip = $client_ip[0] ?: $client_ip[1];   // [0] is proxy, [1] should be the real IP
        $this->failed_attempts = (int)$this->unauthed_user->getPref(self::FAILED_LOGIN_IP_SECTION, $this->client_ip);
        switch (true) {
        case $this->failed_attempts >= 5:
            common_log(LOG_WARNING, sprintf('Multiple failed login attempts for user %s from IP %s - brute force attack?',
                                 $this->unauthed_user->getNickname(), $this->client_ip));
            // 5 seconds is a good max waiting time anyway...
            sleep($this->failed_attempts % 5 + 1);
            break;
        case $this->failed_attempts > 0:
            common_debug(sprintf('Previously failed login on user %s from IP %s - sleeping %u seconds.',
                                 $this->unauthed_user->getNickname(), $this->client_ip, $this->failed_attempts));
            sleep($this->failed_attempts);
            break;
        default:
            // No sleeping if it's our first failed attempt.
        }

        return true;
    }

    public function onEndCheckPassword($nickname, $password, $authenticatedUser)
    {
        if ($authenticatedUser instanceof User) {
            // We'll trust this IP for this user and remove failed logins for the database..
            $authenticatedUser->delPref(self::FAILED_LOGIN_IP_SECTION, $this->client_ip);
            return true;
        }

        // See if we have an unauthed user from before. If not, it might be because the User did
        // not exist yet (such as autoregistering with LDAP, OpenID etc.).
        if ($this->unauthed_user instanceof User) {
            // And if the login failed, we'll increment the attempt count.
            common_debug(sprintf('Failed login tests for user %s from IP %s',
                                 $this->unauthed_user->getNickname(), $this->client_ip));
            $this->unauthed_user->setPref(self::FAILED_LOGIN_IP_SECTION, $this->client_ip, ++$this->failed_attempts);
        }
        return true;
    }

    public function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'AntiBrute',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Anti bruteforce method(s).'));
        return true;
    }
}

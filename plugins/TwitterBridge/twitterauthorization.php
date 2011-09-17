<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for doing OAuth authentication against Twitter
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
 * @author    Zach Copley <zach@status.net>
 * @author    Julien C <chaumond@gmail.com>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Class for doing OAuth authentication against Twitter
 *
 * Peforms the OAuth "dance" between StatusNet and Twitter -- requests a token,
 * authorizes it, and exchanges it for an access token.  It also creates a link
 * (Foreign_link) between the StatusNet user and Twitter user and stores the
 * access token and secret in the link.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Julien C <chaumond@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class TwitterauthorizationAction extends Action
{
    var $twuid        = null;
    var $tw_fields    = null;
    var $access_token = null;
    var $signin       = null;
    var $verifier     = null;

    /**
     * Initialize class members. Looks for 'oauth_token' parameter.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->signin      = $this->boolean('signin');
        $this->oauth_token = $this->arg('oauth_token');
        $this->verifier    = $this->arg('oauth_verifier');

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);

        if (common_logged_in()) {
            $user  = common_current_user();
            $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

            // If there's already a foreign link record and a foreign user
            // it means the accounts are already linked, and this is unecessary.
            // So go back.

            if (isset($flink)) {
                $fuser = $flink->getForeignUser();
                if (!empty($fuser)) {
                    common_redirect(common_local_url('twittersettings'));
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // User was not logged in to StatusNet before

            $this->twuid = $this->trimmed('twuid');

            $this->tw_fields = array('screen_name' => $this->trimmed('tw_fields_screen_name'),
                                     'fullname' => $this->trimmed('tw_fields_fullname'));

            $this->access_token = new OAuthToken($this->trimmed('access_token_key'), $this->trimmed('access_token_secret'));

            $token = $this->trimmed('token');

            if (!$token || $token != common_session_token()) {
                // TRANS: Client error displayed when the session token does not match or is not given.
                $this->showForm(_m('There was a problem with your session token. Try again, please.'));
                return;
            }

            if ($this->arg('create')) {
                if (!$this->boolean('license')) {
                    // TRANS: Form validation error displayed when the checkbox to agree to the license has not been checked.
                    $this->showForm(_m('You cannot register if you do not agree to the license.'),
                                    $this->trimmed('newname'));
                    return;
                }
                $this->createNewUser();
            } else if ($this->arg('connect')) {
                $this->connectNewUser();
            } else {
                common_debug('Twitter bridge - ' . print_r($this->args, true));
                // TRANS: Form validation error displayed when an unhandled error occurs.
                $this->showForm(_m('Something weird happened.'),
                                $this->trimmed('newname'));
            }
        } else {
            // $this->oauth_token is only populated once Twitter authorizes our
            // request token. If it's empty we're at the beginning of the auth
            // process

            if (empty($this->oauth_token)) {
                $this->authorizeRequestToken();
            } else {
                $this->saveAccessToken();
            }
        }
    }

    /**
     * Asks Twitter for a request token, and then redirects to Twitter
     * to authorize it.
     *
     * @return nothing
     */
    function authorizeRequestToken()
    {
        try {
            // Get a new request token and authorize it

            $client  = new TwitterOAuthClient();
            $req_tok = $client->getRequestToken();

            // Sock the request token away in the session temporarily

            $_SESSION['twitter_request_token']        = $req_tok->key;
            $_SESSION['twitter_request_token_secret'] = $req_tok->secret;

            $auth_link = $client->getAuthorizeLink($req_tok, $this->signin);
        } catch (OAuthClientException $e) {
            $msg = sprintf(
                'OAuth client error - code: %1s, msg: %2s',
                $e->getCode(),
                $e->getMessage()
            );
            common_log(LOG_INFO, 'Twitter bridge - ' . $msg);
            $this->serverError(
                // TRANS: Server error displayed when linking to a Twitter account fails.
                _m('Could not link your Twitter account.')
            );
        }

        common_redirect($auth_link);
    }

    /**
     * Called when Twitter returns an authorized request token. Exchanges
     * it for an access token and stores it.
     *
     * @return nothing
     */
    function saveAccessToken()
    {
        // Check to make sure Twitter returned the same request
        // token we sent them

        if ($_SESSION['twitter_request_token'] != $this->oauth_token) {
            $this->serverError(
                // TRANS: Server error displayed when linking to a Twitter account fails because of an incorrect oauth_token.
                _m('Could not link your Twitter account: oauth_token mismatch.')
            );
        }

        $twitter_user = null;

        try {

            $client = new TwitterOAuthClient($_SESSION['twitter_request_token'],
                $_SESSION['twitter_request_token_secret']);

            // Exchange the request token for an access token

            $atok = $client->getAccessToken($this->verifier);

            // Test the access token and get the user's Twitter info

            $client       = new TwitterOAuthClient($atok->key, $atok->secret);
            $twitter_user = $client->verifyCredentials();

        } catch (OAuthClientException $e) {
            $msg = sprintf(
                'OAuth client error - code: %1$s, msg: %2$s',
                $e->getCode(),
                $e->getMessage()
            );
            common_log(LOG_INFO, 'Twitter bridge - ' . $msg);
            $this->serverError(
                // TRANS: Server error displayed when linking to a Twitter account fails.
                _m('Could not link your Twitter account.')
            );
        }

        if (common_logged_in()) {
            // Save the access token and Twitter user info

            $user = common_current_user();
            $this->saveForeignLink($user->id, $twitter_user->id, $atok);
            save_twitter_user($twitter_user->id, $twitter_user->screen_name);

        } else {

            $this->twuid = $twitter_user->id;
            $this->tw_fields = array("screen_name" => $twitter_user->screen_name,
                                     "fullname" => $twitter_user->name);
            $this->access_token = $atok;
            $this->tryLogin();
        }

        // Clean up the the mess we made in the session

        unset($_SESSION['twitter_request_token']);
        unset($_SESSION['twitter_request_token_secret']);

        if (common_logged_in()) {
            common_redirect(common_local_url('twittersettings'));
        }
    }

    /**
     * Saves a Foreign_link between Twitter user and local user,
     * which includes the access token and secret.
     *
     * @param int        $user_id StatusNet user ID
     * @param int        $twuid   Twitter user ID
     * @param OAuthToken $token   the access token to save
     *
     * @return nothing
     */
    function saveForeignLink($user_id, $twuid, $access_token)
    {
        $flink = new Foreign_link();

        $flink->user_id = $user_id;
        $flink->service = TWITTER_SERVICE;

        // delete stale flink, if any
        $result = $flink->find(true);

        if (!empty($result)) {
            $flink->safeDelete();
        }

        $flink->user_id     = $user_id;
        $flink->foreign_id  = $twuid;
        $flink->service     = TWITTER_SERVICE;

        $creds = TwitterOAuthClient::packToken($access_token);

        $flink->credentials = $creds;
        $flink->created     = common_sql_now();

        // Defaults: noticesync on, everything else off

        $flink->set_flags(true, false, false, false);

        $flink_id = $flink->insert();

        if (empty($flink_id)) {
            common_log_db_error($flink, 'INSERT', __FILE__);
            // TRANS: Server error displayed when linking to a Twitter account fails.
            $this->serverError(_m('Could not link your Twitter account.'));
        }

        return $flink_id;
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $this->element('div', 'instructions',
                           // TRANS: Page instruction. %s is the StatusNet sitename.
                           sprintf(_m('This is the first time you have logged into %s so we must connect your Twitter account to a local account. You can either create a new account, or connect with your existing account, if you have one.'), common_config('site', 'name')));
        }
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Twitter Account Setup');
    }

    function showForm($error=null, $username=null)
    {
        $this->error = $error;
        $this->username = $username;

        $this->showPage();
    }

    function showPage()
    {
        parent::showPage();
    }

    /**
     * @fixme much of this duplicates core code, which is very fragile.
     * Should probably be replaced with an extensible mini version of
     * the core registration form.
     */
    function showContent()
    {
        if (!empty($this->message_text)) {
            $this->element('p', null, $this->message);
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_twitter_connect',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('twitterauthorization')));
        $this->elementStart('fieldset', array('id' => 'settings_twitter_connect_options'));
        // TRANS: Fieldset legend.
        $this->element('legend', null, _m('Connection options'));

        $this->hidden('access_token_key', $this->access_token->key);
        $this->hidden('access_token_secret', $this->access_token->secret);
        $this->hidden('twuid', $this->twuid);
        $this->hidden('tw_fields_screen_name', $this->tw_fields['screen_name']);
        $this->hidden('tw_fields_name', $this->tw_fields['fullname']);
        $this->hidden('token', common_session_token());

        // Don't allow new account creation if site is flagged as invite only
	if (common_config('site', 'inviteonly') == false) {
            $this->elementStart('fieldset');
            $this->element('legend', null,
                           // TRANS: Fieldset legend.
                           _m('Create new account'));
            $this->element('p', null,
                           // TRANS: Sub form introduction text.
                          _m('Create a new user with this nickname.'));
            $this->elementStart('ul', 'form_data');

            // Hook point for captcha etc
            Event::handle('StartRegistrationFormData', array($this));

            $this->elementStart('li');
            // TRANS: Field label.
            $this->input('newname', _m('New nickname'),
                         ($this->username) ? $this->username : '',
                         // TRANS: Field title for nickname field.
                         _m('1-64 lowercase letters or numbers, no punctuation or spaces.'));
            $this->elementEnd('li');
            $this->elementStart('li');
            // TRANS: Field label.
            $this->input('email', _m('LABEL','Email'), $this->getEmail(),
                         // TRANS: Field title for e-mail address field.
                         _m('Used only for updates, announcements, '.
                           'and password recovery'));
            $this->elementEnd('li');

            // Hook point for captcha etc
            Event::handle('EndRegistrationFormData', array($this));

            $this->elementEnd('ul');
            // TRANS: Button text for creating a new StatusNet account in the Twitter connect page.
            $this->submit('create', _m('BUTTON','Create'));
            $this->elementEnd('fieldset');
        }

        $this->elementStart('fieldset');
        $this->element('legend', null,
                       // TRANS: Fieldset legend.
                       _m('Connect existing account'));
        $this->element('p', null,
                       // TRANS: Sub form introduction text.
                       _m('If you already have an account, login with your username and password to connect it to your Twitter account.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->input('nickname', _m('Existing nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->password('password', _m('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset');
        $this->element('legend', null,
                       // TRANS: Fieldset legend.
                       _m('License'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('input', array('type' => 'checkbox',
                                      'id' => 'license',
                                      'class' => 'checkbox',
                                      'name' => 'license',
                                      'value' => 'true'));
        $this->elementStart('label', array('class' => 'checkbox', 'for' => 'license'));
        // TRANS: Text for license agreement checkbox.
        // TRANS: %s is the license as configured for the StatusNet site.
        $message = _m('My text and files are available under %s ' .
                     'except this private data: password, ' .
                     'email address, IM address, and phone number.');
        $link = '<a href="' .
                htmlspecialchars(common_config('license', 'url')) .
                '">' .
                htmlspecialchars(common_config('license', 'title')) .
                '</a>';
        $this->raw(sprintf(htmlspecialchars($message), $link));
        $this->elementEnd('label');
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        // TRANS: Button text for connecting an existing StatusNet account in the Twitter connect page..
        $this->submit('connect', _m('BUTTON','Connect'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Get specified e-mail from the form, or the invite code.
     *
     * @return string
     */
    function getEmail()
    {
        $email = $this->trimmed('email');
        if (!empty($email)) {
            return $email;
        }

        // Terrible hack for invites...
        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if ($code) {
                $invite = Invitation::staticGet($code);

                if ($invite && $invite->address_type == 'email') {
                    return $invite->address;
                }
            }
        }
        return '';
    }

    function message($msg)
    {
        $this->message_text = $msg;
        $this->showPage();
    }

    function createNewUser()
    {
        if (!Event::handle('StartRegistrationTry', array($this))) {
            return;
        }

        if (common_config('site', 'closed')) {
            // TRANS: Client error displayed when trying to create a new user while creating new users is not allowed.
            $this->clientError(_m('Registration not allowed.'));
            return;
        }

        $invite = null;

        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if (empty($code)) {
                // TRANS: Client error displayed when trying to create a new user while creating new users is not allowed.
                $this->clientError(_m('Registration not allowed.'));
                return;
            }

            $invite = Invitation::staticGet($code);

            if (empty($invite)) {
                // TRANS: Client error displayed when trying to create a new user with an invalid invitation code.
                $this->clientError(_m('Not a valid invitation code.'));
                return;
            }
        }

        try {
            $nickname = Nickname::normalize($this->trimmed('newname'));
        } catch (NicknameException $e) {
            $this->showForm($e->getMessage());
            return;
        }

        if (!User::allowed_nickname($nickname)) {
            // TRANS: Client error displayed when trying to create a new user with an invalid username.
            $this->showForm(_m('Nickname not allowed.'));
            return;
        }

        if (User::staticGet('nickname', $nickname)) {
            // TRANS: Client error displayed when trying to create a new user with a username that is already in use.
            $this->showForm(_m('Nickname already in use. Try another one.'));
            return;
        }

        $fullname = trim($this->tw_fields['fullname']);

        $args = array('nickname' => $nickname, 'fullname' => $fullname);

        if (!empty($invite)) {
            $args['code'] = $invite->code;
        }

        $email = $this->getEmail();
        if (!empty($email)) {
            $args['email'] = $email;
        }

        $user = User::register($args);

        if (empty($user)) {
            // TRANS: Server error displayed when creating a new user has failed.
            $this->serverError(_m('Error registering user.'));
            return;
        }

        $result = $this->saveForeignLink($user->id,
                                         $this->twuid,
                                         $this->access_token);

        save_twitter_user($this->twuid, $this->tw_fields['screen_name']);

        if (!$result) {
            // TRANS: Server error displayed when connecting a user to a Twitter user has failed.
            $this->serverError(_m('Error connecting user to Twitter.'));
            return;
        }

        common_set_user($user);
        common_real_login(true);

        common_debug('TwitterBridge Plugin - ' .
                     "Registered new user $user->id from Twitter user $this->twuid");

        Event::handle('EndRegistrationTry', array($this));

        common_redirect(common_local_url('showstream', array('nickname' => $user->nickname)),
                        303);
    }

    function connectNewUser()
    {
        $nickname = $this->trimmed('nickname');
        $password = $this->trimmed('password');

        if (!common_check_user($nickname, $password)) {
            // TRANS: Form validation error displayed when connecting an existing user to a Twitter user fails because
            // TRANS: the provided username and/or password are incorrect.
            $this->showForm(_m('Invalid username or password.'));
            return;
        }

        $user = User::staticGet('nickname', $nickname);

        if (!empty($user)) {
            common_debug('TwitterBridge Plugin - ' .
                         "Legit user to connect to Twitter: $nickname");
        }

        $result = $this->saveForeignLink($user->id,
                                         $this->twuid,
                                         $this->access_token);

        save_twitter_user($this->twuid, $this->tw_fields['screen_name']);

        if (!$result) {
            // TRANS: Server error displayed connecting a user to a Twitter user has failed.
            $this->serverError(_m('Error connecting user to Twitter.'));
            return;
        }

        common_debug('TwitterBridge Plugin - ' .
                     "Connected Twitter user $this->twuid to local user $user->id");

        common_set_user($user);
        common_real_login(true);

        $this->goHome($user->nickname);
    }

    function connectUser()
    {
        $user = common_current_user();

        $result = $this->flinkUser($user->id, $this->twuid);

        if (empty($result)) {
            // TRANS: Server error displayed connecting a user to a Twitter user has failed.
            $this->serverError(_m('Error connecting user to Twitter.'));
            return;
        }

        common_debug('TwitterBridge Plugin - ' .
                     "Connected Twitter user $this->twuid to local user $user->id");

        // Return to Twitter connection settings tab
        common_redirect(common_local_url('twittersettings'), 303);
    }

    function tryLogin()
    {
        common_debug('TwitterBridge Plugin - ' .
                     "Trying login for Twitter user $this->twuid.");

        $flink = Foreign_link::getByForeignID($this->twuid,
                                              TWITTER_SERVICE);

        if (!empty($flink)) {
            $user = $flink->getUser();

            if (!empty($user)) {

                common_debug('TwitterBridge Plugin - ' .
                             "Logged in Twitter user $flink->foreign_id as user $user->id ($user->nickname)");

                common_set_user($user);
                common_real_login(true);
                $this->goHome($user->nickname);
            }

        } else {

            common_debug('TwitterBridge Plugin - ' .
                         "No flink found for twuid: $this->twuid - new user");

            $this->showForm(null, $this->bestNewNickname());
        }
    }

    function goHome($nickname)
    {
        $url = common_get_returnto();
        if ($url) {
            // We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $nickname));
        }

        common_redirect($url, 303);
    }

    function bestNewNickname()
    {
        if (!empty($this->tw_fields['fullname'])) {
            $nickname = $this->nicknamize($this->tw_fields['fullname']);
            if ($this->isNewNickname($nickname)) {
                return $nickname;
            }
        }

        return null;
    }

     // Given a string, try to make it work as a nickname

     function nicknamize($str)
     {
         $str = preg_replace('/\W/', '', $str);
         $str = str_replace(array('-', '_'), '', $str);
         return strtolower($str);
     }

    function isNewNickname($str)
    {
        if (!Nickname::isValid($str)) {
            return false;
        }
        if (!User::allowed_nickname($str)) {
            return false;
        }
        if (User::staticGet('nickname', $str)) {
            return false;
        }
        return true;
    }
}

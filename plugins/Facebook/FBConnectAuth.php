<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable Facebook Connect
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/Facebook/FacebookPlugin.php';

class FBConnectauthAction extends Action
{
    var $fbuid      = null;
    var $fb_fields  = null;

    function prepare($args) {
        parent::prepare($args);

        $this->fbuid = getFacebook()->get_loggedin_user();

        if ($this->fbuid > 0) {
            $this->fb_fields = $this->getFacebookFields($this->fbuid,
                                                        array('first_name', 'last_name', 'name'));
        } else {
            list($proxy, $ip) = common_client_ip();

            common_log(LOG_WARNING, 'Facebook Connect Plugin - ' .
                       "Failed auth attempt, proxy = $proxy, ip = $ip.");

            $this->clientError(_m('You must be logged into Facebook to ' .
                                  'use Facebook Connect.'));
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            // User is already logged in.  Does she already have a linked Facebook acct?
            $flink = Foreign_link::getByForeignID($this->fbuid, FACEBOOK_CONNECT_SERVICE);

            if (!empty($flink)) {
                // User already has a linked Facebook account and shouldn't be here
                common_debug('Facebook Connect Plugin - ' .
                             'There is already a local user (' . $flink->user_id .
                             ') linked with this Facebook (' . $this->fbuid . ').');

                // We don't want these cookies
                getFacebook()->clear_cookie_state();

                $this->clientError(_m('There is already a local user linked with this Facebook account.'));
            } else {

                // User came from the Facebook connect settings tab, and
                // probably just wants to link/relink their Facebook account
                $this->connectUser();
            }

        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_m('There was a problem with your session token. Try again, please.'));
                return;
            }
            if ($this->arg('create')) {
                if (!$this->boolean('license')) {
                    $this->showForm(_m('You can\'t register if you don\'t agree to the license.'),
                                    $this->trimmed('newname'));
                    return;
                }
                $this->createNewUser();
            } else if ($this->arg('connect')) {
                $this->connectNewUser();
            } else {
                common_debug('Facebook Connect Plugin - ' .
                             print_r($this->args, true));
                $this->showForm(_m('An unknown error has occured.'),
                                $this->trimmed('newname'));
            }
        } else {
            $this->tryLogin();
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $this->element('div', 'instructions',
                           // TRANS: %s is the site name.
                           sprintf(_m('This is the first time you\'ve logged into %s so we must connect your Facebook to a local account. You can either create a new account, or connect with your existing account, if you have one.'), common_config('site', 'name')));
        }
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Facebook Account Setup');
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
                                          'id' => 'form_settings_facebook_connect',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('FBConnectAuth')));
        $this->elementStart('fieldset', array('id' => 'settings_facebook_connect_options'));
        // TRANS: Legend.
        $this->element('legend', null, _m('Connection options'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('input', array('type' => 'checkbox',
                                      'id' => 'license',
                                      'class' => 'checkbox',
                                      'name' => 'license',
                                      'value' => 'true'));
        $this->elementStart('label', array('class' => 'checkbox', 'for' => 'license'));
        // TRANS: %s is the name of the license used by the user for their status updates.
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

        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->element('legend', null,
                       // TRANS: Legend.
                       _m('Create new account'));
        $this->element('p', null,
                       _m('Create a new user with this nickname.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->input('newname', _m('New nickname'),
                     ($this->username) ? $this->username : '',
                     _m('1-64 lowercase letters or numbers, no punctuation or spaces'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Submit button.
        $this->submit('create', _m('BUTTON','Create'));
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset');
        // TRANS: Legend.
        $this->element('legend', null,
                       _m('Connect existing account'));
        $this->element('p', null,
                       _m('If you already have an account, login with your username and password to connect it to your Facebook.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->input('nickname', _m('Existing nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->password('password', _m('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Submit button.
        $this->submit('connect', _m('BUTTON','Connect'));
        $this->elementEnd('fieldset');

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function message($msg)
    {
        $this->message_text = $msg;
        $this->showPage();
    }

    function createNewUser()
    {
        if (common_config('site', 'closed')) {
            // TRANS: Client error trying to register with registrations not allowed.
            $this->clientError(_m('Registration not allowed.'));
            return;
        }

        $invite = null;

        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if (empty($code)) {
                // TRANS: Client error trying to register with registrations 'invite only'.
                $this->clientError(_m('Registration not allowed.'));
                return;
            }

            $invite = Invitation::staticGet($code);

            if (empty($invite)) {
                // TRANS: Client error trying to register with an invalid invitation code.
                $this->clientError(_m('Not a valid invitation code.'));
                return;
            }
        }

        try {
            $nickname = Nickname::normalize($this->trimmed('newname'));
        } catch (NicknameException $e) {
            $this->showForm($e->getMessage());
        }

        if (!User::allowed_nickname($nickname)) {
            $this->showForm(_m('Nickname not allowed.'));
            return;
        }

        if (User::staticGet('nickname', $nickname)) {
            $this->showForm(_m('Nickname already in use. Try another one.'));
            return;
        }

        $fullname = trim($this->fb_fields['firstname'] .
            ' ' . $this->fb_fields['lastname']);

        $args = array('nickname' => $nickname, 'fullname' => $fullname);

        if (!empty($invite)) {
            $args['code'] = $invite->code;
        }

        $user = User::register($args);

        $result = $this->flinkUser($user->id, $this->fbuid);

        if (!$result) {
            $this->serverError(_m('Error connecting user to Facebook.'));
            return;
        }

        common_set_user($user);
        common_real_login(true);

        common_debug('Facebook Connect Plugin - ' .
                     "Registered new user $user->id from Facebook user $this->fbuid");

        common_redirect(common_local_url('showstream', array('nickname' => $user->nickname)),
                        303);
    }

    function connectNewUser()
    {
        $nickname = $this->trimmed('nickname');
        $password = $this->trimmed('password');

        if (!common_check_user($nickname, $password)) {
            $this->showForm(_m('Invalid username or password.'));
            return;
        }

        $user = User::staticGet('nickname', $nickname);

        if (!empty($user)) {
            common_debug('Facebook Connect Plugin - ' .
                         "Legit user to connect to Facebook: $nickname");
        }

        $result = $this->flinkUser($user->id, $this->fbuid);

        if (!$result) {
            $this->serverError(_m('Error connecting user to Facebook.'));
            return;
        }

        common_debug('Facebook Connnect Plugin - ' .
                     "Connected Facebook user $this->fbuid to local user $user->id");

        common_set_user($user);
        common_real_login(true);

        $this->goHome($user->nickname);
    }

    function connectUser()
    {
        $user = common_current_user();

        $result = $this->flinkUser($user->id, $this->fbuid);

        if (empty($result)) {
            $this->serverError(_m('Error connecting user to Facebook.'));
            return;
        }

        common_debug('Facebook Connect Plugin - ' .
                     "Connected Facebook user $this->fbuid to local user $user->id");

        // Return to Facebook connection settings tab
        common_redirect(common_local_url('FBConnectSettings'), 303);
    }

    function tryLogin()
    {
        common_debug('Facebook Connect Plugin - ' .
                     "Trying login for Facebook user $this->fbuid.");

        $flink = Foreign_link::getByForeignID($this->fbuid, FACEBOOK_CONNECT_SERVICE);

        if (!empty($flink)) {
            $user = $flink->getUser();

            if (!empty($user)) {

                common_debug('Facebook Connect Plugin - ' .
                             "Logged in Facebook user $flink->foreign_id as user $user->id ($user->nickname)");

                common_set_user($user);
                common_real_login(true);
                $this->goHome($user->nickname);
            }

        } else {

            common_debug('Facebook Connect Plugin - ' .
                         "No flink found for fbuid: $this->fbuid - new user");

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

    function flinkUser($user_id, $fbuid)
    {
        $flink = new Foreign_link();
        $flink->user_id = $user_id;
        $flink->foreign_id = $fbuid;
        $flink->service = FACEBOOK_CONNECT_SERVICE;
        $flink->created = common_sql_now();

        $flink_id = $flink->insert();

        return $flink_id;
    }

    function bestNewNickname()
    {
        if (!empty($this->fb_fields['name'])) {
            $nickname = $this->nicknamize($this->fb_fields['name']);
            if ($this->isNewNickname($nickname)) {
                return $nickname;
            }
        }

        // Try the full name

        $fullname = trim($this->fb_fields['firstname'] .
            ' ' . $this->fb_fields['lastname']);

        if (!empty($fullname)) {
            $fullname = $this->nicknamize($fullname);
            if ($this->isNewNickname($fullname)) {
                return $fullname;
            }
        }

        return null;
    }

     /**
      * Given a string, try to make it work as a nickname
      */
     function nicknamize($str)
     {
         $str = preg_replace('/\W/', '', $str);
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

    // XXX: Consider moving this to lib/facebookutil.php
    function getFacebookFields($fb_uid, $fields) {
        try {

            $facebook = getFacebook();

            $infos = $facebook->api_client->users_getInfo($fb_uid, $fields);

            if (empty($infos)) {
                return null;
            }
            return reset($infos);

        } catch (Exception $e) {
            common_log(LOG_WARNING, 'Facebook Connect Plugin - ' .
                       "Facebook client failure when requesting " .
                join(",", $fields) . " on uid " . $fb_uid .
                    " : ". $e->getMessage());
            return null;
        }
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once('Auth/OpenID.php');
require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/Server.php');
require_once('Auth/OpenID/SReg.php');
require_once('Auth/OpenID/MySQLStore.php');

// About one year cookie expiry

define('OPENID_COOKIE_EXPIRY', round(365.25 * 24 * 60 * 60));
define('OPENID_COOKIE_KEY', 'lastusedopenid');

function oid_store()
{
    static $store = null;
    if (!$store) {
        // Can't be called statically
        $user = new User();
        $conn = $user->getDatabaseConnection();
        $store = new Auth_OpenID_MySQLStore($conn);
    }
    return $store;
}

function oid_consumer()
{
    $store = oid_store();
    $consumer = new Auth_OpenID_Consumer($store);
    return $consumer;
}

function oid_server()
{
    $store = oid_store();
    $server = new Auth_OpenID_Server($store, common_local_url('openidserver'));
    return $server;
}

function oid_clear_last()
{
    oid_set_last('');
}

function oid_set_last($openid_url)
{
    common_set_cookie(OPENID_COOKIE_KEY,
                     $openid_url,
                     time() + OPENID_COOKIE_EXPIRY);
}

function oid_get_last()
{
    if (empty($_COOKIE[OPENID_COOKIE_KEY])) {
        return null;
    }
    $openid_url = $_COOKIE[OPENID_COOKIE_KEY];
    if ($openid_url && strlen($openid_url) > 0) {
        return $openid_url;
    } else {
        return null;
    }
}

function oid_link_user($id, $canonical, $display)
{
    global $_PEAR;

    $oid = new User_openid();
    $oid->user_id = $id;
    $oid->canonical = $canonical;
    $oid->display = $display;
    $oid->created = common_sql_now();

    if (!$oid->insert()) {
        $err = &$_PEAR->getStaticProperty('DB_DataObject','lastError');
        return false;
    }

    return true;
}

function oid_get_user($openid_url)
{
    $user = null;
    $oid = User_openid::getKV('canonical', $openid_url);
    if ($oid) {
        $user = User::getKV('id', $oid->user_id);
    }
    return $user;
}

function oid_check_immediate($openid_url, $backto=null)
{
    if (!$backto) {
        $action = $_REQUEST['action'];
        $args = common_copy_args($_GET);
        unset($args['action']);
        $backto = common_local_url($action, $args);
    }

    common_ensure_session();

    $_SESSION['openid_immediate_backto'] = $backto;

    oid_authenticate($openid_url,
                     'finishimmediate',
                     true);
}

function oid_authenticate($openid_url, $returnto, $immediate=false)
{

    $consumer = oid_consumer();

    if (!$consumer) {
        // TRANS: OpenID plugin server error.
        common_server_error(_m('Cannot instantiate OpenID consumer object.'));
        return false;
    }

    common_ensure_session();

    $auth_request = $consumer->begin($openid_url);

    // Handle failure status return values.
    if (!$auth_request) {
        common_log(LOG_ERR, __METHOD__ . ": mystery fail contacting $openid_url");
        // TRANS: OpenID plugin message. Given when an OpenID is not valid.
        return _m('Not a valid OpenID.');
    } else if (Auth_OpenID::isFailure($auth_request)) {
        common_log(LOG_ERR, __METHOD__ . ": OpenID fail to $openid_url: $auth_request->message");
        // TRANS: OpenID plugin server error. Given when the OpenID authentication request fails.
        // TRANS: %s is the failure message.
        return sprintf(_m('OpenID failure: %s.'), $auth_request->message);
    }

    $sreg_request = Auth_OpenID_SRegRequest::build(// Required
                                                   array(),
                                                   // Optional
                                                   array('nickname',
                                                         'email',
                                                         'fullname',
                                                         'language',
                                                         'timezone',
                                                         'postcode',
                                                         'country'));

    if ($sreg_request) {
        $auth_request->addExtension($sreg_request);
    }

    $requiredTeam = common_config('openid', 'required_team');
    if ($requiredTeam) {
        // LaunchPad OpenID extension
        $team_request = new Auth_OpenID_TeamsRequest(array($requiredTeam));
        if ($team_request) {
            $auth_request->addExtension($team_request);
        }
    }

    $trust_root = common_root_url(true);
    $process_url = common_local_url($returnto);

    // Net::OpenID::Server as used on LiveJournal appears to incorrectly
    // reject POST requests for data submissions that OpenID 1.1 specs
    // as GET, although 2.0 allows them:
    // https://rt.cpan.org/Public/Bug/Display.html?id=42202
    //
    // Our OpenID libraries would have switched in the redirect automatically
    // if it were detecting 1.1 compatibility mode, however the server is
    // advertising itself as 2.0-compatible, so we got switched to the POST.
    //
    // Since the GET should always work anyway, we'll just take out the
    // autosubmitter for now.
    // 
    //if ($auth_request->shouldSendRedirect()) {
        $redirect_url = $auth_request->redirectURL($trust_root,
                                                   $process_url,
                                                   $immediate);
        if (!$redirect_url) {
        } else if (Auth_OpenID::isFailure($redirect_url)) {
            // TRANS: OpenID plugin server error. Given when the OpenID authentication request cannot be redirected.
            // TRANS: %s is the failure message.
            return sprintf(_m('Could not redirect to server: %s.'), $redirect_url->message);
        } else {
            common_redirect($redirect_url, 303);
        }
    /*
    } else {
        // Generate form markup and render it.
        $form_id = 'openid_message';
        $form_html = $auth_request->formMarkup($trust_root, $process_url,
                                               $immediate, array('id' => $form_id));

        // XXX: This is cheap, but things choke if we don't escape ampersands
        // in the HTML attributes

        $form_html = preg_replace('/&/', '&amp;', $form_html);

        // Display an error if the form markup couldn't be generated;
        // otherwise, render the HTML.
        if (Auth_OpenID::isFailure($form_html)) {
            // TRANS: OpenID plugin server error if the form markup could not be generated.
            // TRANS: %s is the failure message.
            common_server_error(sprintf(_m('Could not create OpenID form: %s'), $form_html->message));
        } else {
            $action = new AutosubmitAction(); // see below
            $action->form_html = $form_html;
            $action->form_id = $form_id;
            $action->prepare(array('action' => 'autosubmit'));
            $action->handle(array('action' => 'autosubmit'));
        }
    }
    */
}

// Half-assed attempt at a module-private function

function _oid_print_instructions()
{
    common_element('div', 'instructions',
                   // TRANS: OpenID plugin user instructions.
                   _m('This form should automatically submit itself. '.
                      'If not, click the submit button to go to your '.
                      'OpenID provider.'));
}

/**
 * Update a user from sreg parameters
 * @param User $user
 * @param array $sreg fields from OpenID sreg response
 * @access private
 */
function oid_update_user($user, $sreg)
{
    $profile = $user->getProfile();

    $orig_profile = clone($profile);

    if (!empty($sreg['fullname']) && strlen($sreg['fullname']) <= 255) {
        $profile->fullname = $sreg['fullname'];
    }

    if (!empty($sreg['country'])) {
        if ($sreg['postcode']) {
            // XXX: use postcode to get city and region
            // XXX: also, store postcode somewhere -- it's valuable!
            $profile->location = $sreg['postcode'] . ', ' . $sreg['country'];
        } else {
            $profile->location = $sreg['country'];
        }
    }

    // XXX save language if it's passed
    // XXX save timezone if it's passed

    if (!$profile->update($orig_profile)) {
        // TRANS: OpenID plugin server error.
        common_server_error(_m('Error saving the profile.'));
        return false;
    }

    $orig_user = clone($user);

    if (!empty($sreg['email']) && Validate::email($sreg['email'], common_config('email', 'check_domain'))) {
        $user->email = $sreg['email'];
    }

    if (!$user->update($orig_user)) {
        // TRANS: OpenID plugin server error.
        common_server_error(_m('Error saving the user.'));
        return false;
    }

    return true;
}

function oid_assert_allowed($url)
{
    $blacklist = common_config('openid', 'blacklist');
    $whitelist = common_config('openid', 'whitelist');

    if (empty($blacklist)) {
        $blacklist = array();
    }

    if (empty($whitelist)) {
        $whitelist = array();
    }

    foreach ($blacklist as $pattern) {
        if (preg_match("/$pattern/", $url)) {
            common_log(LOG_INFO, "Matched OpenID blacklist pattern {$pattern} with {$url}");
            foreach ($whitelist as $exception) {
                if (preg_match("/$exception/", $url)) {
                    common_log(LOG_INFO, "Matched OpenID whitelist pattern {$exception} with {$url}");
                    return;
                }
            }
            // TRANS: OpenID plugin client exception (403).
            throw new ClientException(_m('Unauthorized URL used for OpenID login.'), 403);
        }
    }

    return;
}

/**
 * Check the teams available in the given OpenID response
 * Using Launchpad's OpenID teams extension
 *
 * @return boolean whether this user is acceptable
 */
function oid_check_teams($response)
{
    $requiredTeam = common_config('openid', 'required_team');
    if ($requiredTeam) {
        $team_resp = new Auth_OpenID_TeamsResponse($response);
        if ($team_resp) {
            $teams = $team_resp->getTeams();
        } else {
            $teams = array();
        }

        $match = in_array($requiredTeam, $teams);
        $is = $match ? 'is' : 'is not';
        common_log(LOG_DEBUG, "Remote user $is in required team $requiredTeam: [" . implode(', ', $teams) . "]");

        return $match;
    }

    return true;
}

class AutosubmitAction extends Action
{
    var $form_html = null;
    var $form_id = null;

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        // TRANS: Title
        return _m('OpenID Login Submission');
    }

    function showContent()
    {
        $this->raw('<p style="margin: 20px 80px">');
        // @todo FIXME: This would be better using standard CSS class, but the present theme's a bit scary.
        $this->element('img', array('src' => Theme::path('images/icons/icon_processing.gif', 'base'),
                                    // for some reason the base CSS sets <img>s as block display?!
                                    'style' => 'display: inline'));
        // TRANS: OpenID plugin message used while requesting authorization user's OpenID login provider.
        $this->text(_m('Requesting authorization from your login provider...'));
        $this->raw('</p>');
        $this->raw('<p style="margin-top: 60px; font-style: italic">');
        // TRANS: OpenID plugin message. User instruction while requesting authorization user's OpenID login provider.
        $this->text(_m('If you are not redirected to your login provider in a few seconds, try pushing the button below.'));
        $this->raw('</p>');
        $this->raw($this->form_html);
    }

    function showScripts()
    {
        parent::showScripts();
        $this->element('script', null,
                       'document.getElementById(\'' . $this->form_id . '\').submit();');
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010-2011, StatusNet, Inc.
 *
 * A plugin for integrating Facebook with StatusNet. Includes single-sign-on
 * and publishing notices to Facebook using Facebook's Graph API.
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define("FACEBOOK_SERVICE", 2);

/**
 * Main class for Facebook Bridge plugin
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class FacebookBridgePlugin extends Plugin
{
    public $appId;  // Facebook application ID
    public $secret; // Facebook application secret

    public $facebook = null; // Facebook application instance
    public $dir      = null; // Facebook plugin dir

    /**
     * Initializer for this plugin
     *
     * Gets an instance of the Facebook API client object
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {

        // Allow the id and key to be passed in
        // Control panel will override

        if (isset($this->appId)) {
            $appId = common_config('facebook', 'appid');
            if (empty($appId)) {
                Config::save(
                    'facebook',
                    'appid',
                    $this->appId
                );
            }
        }

        if (isset($this->secret)) {
            $secret = common_config('facebook', 'secret');
            if (empty($secret)) {
                Config::save('facebook', 'secret', $this->secret);
            }
        }

        $this->facebook = Facebookclient::getFacebook(
            $this->appId,
            $this->secret
        );

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Facebook': // Facebook PHP SDK
            include_once $dir . '/extlib/base_facebook.php';
            include_once $dir . '/extlib/facebook.php';
            return false;
        }

        return parent::onAutoload($cls);
    }

    /**
     * Database schema setup
     *
     * We maintain a table mapping StatusNet notices to Facebook items
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('notice_to_item', Notice_to_item::schemaDef());
        return true;
    }

    /*
     * Does this $action need the Facebook JavaScripts?
     */
    function needsScripts($action)
    {
        static $needy = array(
            'FacebookloginAction',
            'FacebookfinishloginAction',
            'FacebookadminpanelAction',
            'FacebooksettingsAction'
        );

        if (in_array(get_class($action), $needy)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
    {
        // Always add the admin panel route
        $m->connect('panel/facebook', array('action' => 'facebookadminpanel'));

        $m->connect(
            'main/facebooklogin',
            array('action' => 'facebooklogin')
        );
        $m->connect(
            'main/facebookfinishlogin',
            array('action' => 'facebookfinishlogin')
        );
        $m->connect(
            'settings/facebook',
            array('action' => 'facebooksettings')
        );
        $m->connect(
            'facebook/deauthorize',
            array('action' => 'facebookdeauthorize')
        );

        return true;
    }

    /*
     * Add a login tab for Facebook, but only if there's a Facebook
     * application defined for the plugin to use.
     *
     * @param Action $action the current action
     *
     * @return void
     */
    function onEndLoginGroupNav($action)
    {
        $action_name = $action->trimmed('action');

        if ($this->hasApplication()) {

            $action->menuItem(
                // TRANS: Menu item for "Facebook" login.
                common_local_url('facebooklogin'),
                _m('MENU', 'Facebook'),
                // TRANS: Menu title for "Facebook" login.
                _m('Login or register using Facebook.'),
               'facebooklogin' === $action_name
            );
        }

        return true;
    }

    /**
     * If the plugin's installed, this should be accessible to admins
     */
    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'facebook') {
            $isOK = true;
            return false;
        }

        return true;
    }

    /**
     * Add a Facebook tab to the admin panels
     *
     * @param Widget $nav Admin panel nav
     *
     * @return boolean hook value
     */
    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('facebook')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(
                common_local_url('facebookadminpanel'),
                // TRANS: Menu item for "Facebook" in administration panel.
                _m('MENU','Facebook'),
                // TRANS: Menu title for "Facebook" in administration panel.
                _m('Facebook integration configuration.'),
                $action_name == 'facebookadminpanel',
                'nav_facebook_admin_panel'
            );
        }

        return true;
    }

    /*
     * Add a tab for user-level Facebook settings if the user
     * has a link to Facebook
     *
     * @param Action $action the current action
     *
     * @return void
     */
    function onEndConnectSettingsNav($action)
    {
        if ($this->hasApplication()) {
            $action_name = $action->trimmed('action');

            $user = common_current_user();

            $flink = null;

            if (!empty($user)) {
                $flink = Foreign_link::getByUserID(
                    $user->id,
                    FACEBOOK_SERVICE
                );
            }

            if (!empty($flink)) {

                $action->menuItem(
                    common_local_url('facebooksettings'),
                    // TRANS: Menu item for "Facebook" in user settings.
                    _m('MENU','Facebook'),
                    // TRANS: Menu title for "Facebook" in user settings.
                    _m('Facebook settings.'),
                    $action_name === 'facebooksettings'
                );
            }
        }
    }

    /*
     * Is there a Facebook application for the plugin to use?
     *
     * Checks to see if a Facebook application ID and secret
     * have been configured and a valid Facebook API client
     * object exists.
     *
     */
    function hasApplication()
    {
        if (!empty($this->facebook)) {

            $appId  = $this->facebook->getAppId();
            $secret = $this->facebook->getApiSecret();

            if (!empty($appId) && !empty($secret)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Output a Facebook div for the Facebook JavaSsript SDK to use
     *
     * @param Action $action the current action
     *
     */
    function onStartShowHeader($action)
    {
        // output <div id="fb-root"></div> as close to <body> as possible
        $action->element('div', array('id' => 'fb-root'));
        return true;
    }

    /*
     * Load the Facebook JavaScript SDK on pages that need them.
     *
     * @param Action $action the current action
     *
     */
    function onEndShowScripts($action)
    {
        if ($this->needsScripts($action)) {

            $action->script('https://connect.facebook.net/en_US/all.js');

            $script = <<<ENDOFSCRIPT
function setCookie(name, value) {
    var date = new Date();
    date.setTime(date.getTime() + (5 * 60 * 1000)); // 5 mins
    var expires = "; expires=" + date.toGMTString();
    document.cookie = name + "=" + value + expires + "; path=/";
}

FB.init({appId: %1\$s, status: true, cookie: true, xfbml: true, oauth: true});

$('#facebook_button').bind('click', function(event) {

    event.preventDefault();

    FB.login(function(response) {
        if (response.authResponse) {
            // put the access token in a cookie for the next step
            setCookie('fb_access_token', response.authResponse.accessToken);
            window.location.href = '%2\$s';
        } else {
            // NOP (user cancelled login)
        }
    }, {scope:'read_stream,publish_stream,offline_access,user_status,user_location,user_website,email'});
});
ENDOFSCRIPT;

            $action->inlineScript(
                sprintf(
                    $script,
                    json_encode($this->facebook->getAppId()),
                    common_local_url('facebookfinishlogin')
                )
            );
        }
    }

    /*
     * Log the user out of Facebook, per the Facebook authentication guide
     *
     * @param Action action the current action
     */
    function onStartLogout($action)
    {
        if ($this->hasApplication()) {

            $cur = common_current_user();
            $flink = Foreign_link::getByUserID($cur->id, FACEBOOK_SERVICE);

            if (!empty($flink)) {

                $this->facebook->setAccessToken($flink->credentials);

                if (common_config('singleuser', 'enabled')) {
                    $user = User::singleUser();

                    $destination = common_local_url(
                        'showstream',
                        array('nickname' => $user->nickname)
                    );
                } else {
                    $destination = common_local_url('public');
                }

                $logoutUrl = $this->facebook->getLogoutUrl(
                    array('next' => $destination)
                );

                common_log(
                    LOG_INFO,
                    sprintf(
                        "Logging user out of Facebook (fbuid = %s)",
                        $fbuid
                    ),
                    __FILE__
                );

                $action->logout();

                common_redirect($logoutUrl, 303);
            }

            return true;
        }
    }

    /*
     * Add fbml namespace to our HTML, so Facebook's JavaScript SDK can parse
     * and render XFBML tags
     *
     * @param Action    $action   the current action
     * @param array     $attrs    array of attributes for the HTML tag
     *
     * @return nothing
     */
    function onStartHtmlElement($action, $attrs) {

        if ($this->needsScripts($action)) {
            $attrs = array_merge(
                $attrs,
                array('xmlns:fb' => 'http://www.facebook.com/2008/fbml')
            );
        }

        return true;
    }

    /**
     * Add a Facebook queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        if (self::hasApplication() && $notice->isLocal() && $notice->inScope(null)) {
            array_push($transports, 'facebook');
        }
        return true;
    }

    /**
     * Register Facebook notice queue handler
     *
     * @param QueueManager $manager
     *
     * @return boolean hook return
     */
    function onEndInitializeQueueManager($manager)
    {
        if (self::hasApplication()) {
            $manager->connect('facebook', 'FacebookQueueHandler');
        }
        return true;
    }

    /**
     * If a notice gets deleted, remove the Notice_to_item mapping and
     * delete the item on Facebook
     *
     * @param User   $user   The user doing the deleting
     * @param Notice $notice The notice getting deleted
     *
     * @return boolean hook value
     */
    function onStartDeleteOwnNotice(User $user, Notice $notice)
    {
        $client = new Facebookclient($notice);
        $client->streamRemove();

        return true;
    }

    /**
     * Notify remote users when their notices get favorited.
     *
     * @param Profile or User $profile of local user doing the faving
     * @param Notice $notice being favored
     * @return hook return value
     */
    function onEndFavorNotice(Profile $profile, Notice $notice)
    {
        $client = new Facebookclient($notice, $profile);
        $client->like();

        return true;
    }

    /**
     * Notify remote users when their notices get de-favorited.
     *
     * @param Profile $profile Profile person doing the de-faving
     * @param Notice  $notice  Notice being favored
     *
     * @return hook return value
     */
    function onEndDisfavorNotice(Profile $profile, Notice $notice)
    {
        $client = new Facebookclient($notice, $profile);
        $client->unLike();

        return true;
    }

    /**
     * Add links in the user's profile block to their Facebook profile URL.
     *
     * @param Profile $profile The profile being shown
     * @param Array   &$links  Writeable array of arrays (href, text, image).
     *
     * @return boolean hook value (true)
     */

    function onOtherAccountProfiles($profile, &$links)
    {
        $fuser = null;

        $flink = Foreign_link::getByUserID($profile->id, FACEBOOK_SERVICE);

        if (!empty($flink)) {

            $fuser = $this->getFacebookUser($flink->foreign_id);

            if (!empty($fuser)) {
                $links[] = array("href" => $fuser->link,
                                 "text" => sprintf(_("%s on Facebook"), $fuser->name),
                                 "image" => $this->path("images/f_logo.png"));
            }
        }

        return true;
    }

    function getFacebookUser($id) {

        $key = Cache::key(sprintf("FacebookBridgePlugin:userdata:%s", $id));

        $c = Cache::instance();

        if ($c) {
            $obj = $c->get($key);
            if ($obj) {
                return $obj;
            }
        }

        $url = sprintf("https://graph.facebook.com/%s", $id);
        $client = new HTTPClient();
        $resp = $client->get($url);

        if (!$resp->isOK()) {
            return null;
        }

        $user = json_decode($resp->getBody());

        if ($user->error) {
            return null;
        }

        if ($c) {
            $c->set($key, $user);
        }

        return $user;
    }

    /*
     * Add version info for this plugin
     *
     * @param array &$versions    plugin version descriptions
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array(
            'name' => 'Facebook Bridge',
            'version' => GNUSOCIAL_VERSION,
            'author' => 'Craig Andrews, Zach Copley',
            'homepage' => 'http://status.net/wiki/Plugin:FacebookBridge',
            'rawdescription' =>
             // TRANS: Plugin description.
            _m('A plugin for integrating StatusNet with Facebook.')
        );

        return true;
    }
}

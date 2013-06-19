<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @copyright 2009-2010 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Plugin for sending and importing Twitter statuses
 *
 * This class allows users to link their Twitter accounts
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Julien C <chaumond@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @link     http://twitter.com/
 */
class TwitterBridgePlugin extends Plugin
{
    const VERSION = STATUSNET_VERSION;
    public $adminImportControl = false; // Should the 'import' checkbox be exposed in the admin panel?

    /**
     * Initializer for the plugin.
     */
    function initialize()
    {
        // Allow the key and secret to be passed in
        // Control panel will override

        if (isset($this->consumer_key)) {
            $key = common_config('twitter', 'consumer_key');
            if (empty($key)) {
                Config::save('twitter', 'consumer_key', $this->consumer_key);
            }
        }

        if (isset($this->consumer_secret)) {
            $secret = common_config('twitter', 'consumer_secret');
            if (empty($secret)) {
                Config::save(
                    'twitter',
                    'consumer_secret',
                    $this->consumer_secret
                );
            }
        }
    }

    /**
     * Check to see if there is a consumer key and secret defined
     * for Twitter integration.
     *
     * @return boolean result
     */
    static function hasKeys()
    {
        $ckey    = common_config('twitter', 'consumer_key');
        $csecret = common_config('twitter', 'consumer_secret');

        if (empty($ckey) && empty($csecret)) {
            $ckey    = common_config('twitter', 'global_consumer_key');
            $csecret = common_config('twitter', 'global_consumer_secret');
        }

        if (!empty($ckey) && !empty($csecret)) {
            return true;
        }

        return false;
    }

    /**
     * Add Twitter-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('panel/twitter', array('action' => 'twitteradminpanel'));

        if (self::hasKeys()) {
            $m->connect(
                'twitter/authorization',
                array('action' => 'twitterauthorization')
            );
            $m->connect(
                'settings/twitter', array(
                    'action' => 'twittersettings'
                    )
                );
            if (common_config('twitter', 'signin')) {
                $m->connect(
                    'main/twitterlogin',
                    array('action' => 'twitterlogin')
                );
            }
        }

        return true;
    }

    /*
     * Add a login tab for 'Sign in with Twitter'
     *
     * @param Action $action the current action
     *
     * @return void
     */
    function onEndLoginGroupNav($action)
    {
        $action_name = $action->trimmed('action');

        if (self::hasKeys() && common_config('twitter', 'signin')) {
            $action->menuItem(
                common_local_url('twitterlogin'),
                // TRANS: Menu item in login navigation.
                _m('MENU','Twitter'),
                // TRANS: Title for menu item in login navigation.
                _m('Login or register using Twitter.'),
                'twitterlogin' === $action_name
            );
        }

        return true;
    }

    /**
     * Add the Twitter Settings page to the Connect Settings menu
     *
     * @param Action $action The calling page
     *
     * @return boolean hook return
     */
    function onEndConnectSettingsNav($action)
    {
        if (self::hasKeys()) {
            $action_name = $action->trimmed('action');

            $action->menuItem(
                common_local_url('twittersettings'),
                // TRANS: Menu item in connection settings navigation.
                _m('MENU','Twitter'),
                // TRANS: Title for menu item in connection settings navigation.
                _m('Twitter integration options'),
                $action_name === 'twittersettings'
            );
        }
        return true;
    }

    /**
     * Automatically load the actions and libraries used by the Twitter bridge
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls) {
        case 'TwittersettingsAction':
        case 'TwitterauthorizationAction':
        case 'TwitterloginAction':
        case 'TwitteradminpanelAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'TwitterOAuthClient':
        case 'TwitterQueueHandler':
        case 'TweetInQueueHandler':
        case 'TwitterImport':
        case 'JsonStreamReader':
        case 'TwitterStreamReader':
            include_once $dir . '/' . strtolower($cls) . '.php';
            return false;
        case 'TwitterSiteStream':
        case 'TwitterUserStream':
            include_once $dir . '/twitterstreamreader.php';
            return false;
        case 'Notice_to_status':
        case 'Twitter_synch_status':
            include_once $dir . '/' . $cls . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Add a Twitter queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        if (self::hasKeys() && $notice->isLocal() && $notice->inScope(null)) {
            // Avoid a possible loop
            if ($notice->source != 'twitter') {
                array_push($transports, 'twitter');
            }
        }
        return true;
    }

    /**
     * Add Twitter bridge daemons to the list of daemons to start
     *
     * @param array $daemons the list fo daemons to run
     *
     * @return boolean hook return
     */
    function onGetValidDaemons(&$daemons)
    {
        if (self::hasKeys()) {
            array_push(
                $daemons,
                INSTALLDIR
                . '/plugins/TwitterBridge/daemons/synctwitterfriends.php'
            );
            if (common_config('twitterimport', 'enabled')) {
                array_push(
                    $daemons,
                    INSTALLDIR
                    . '/plugins/TwitterBridge/daemons/twitterstatusfetcher.php'
                    );
            }
        }

        return true;
    }

    /**
     * Register Twitter notice queue handler
     *
     * @param QueueManager $manager
     *
     * @return boolean hook return
     */
    function onEndInitializeQueueManager($manager)
    {
        if (self::hasKeys()) {
            // Outgoing notices -> twitter
            $manager->connect('twitter', 'TwitterQueueHandler');

            // Incoming statuses <- twitter
            $manager->connect('tweetin', 'TweetInQueueHandler');
        }
        return true;
    }

    /**
     * If the plugin's installed, this should be accessible to admins
     */
    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'twitter') {
            $isOK = true;
            return false;
        }
        return true;
    }

    /**
     * Add a Twitter tab to the admin panel
     *
     * @param Widget $nav Admin panel nav
     *
     * @return boolean hook value
     */

    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('twitter')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(
                common_local_url('twitteradminpanel'),
                // TRANS: Menu item in administrative panel that leads to the Twitter bridge configuration.
                _m('Twitter'),
                // TRANS: Menu item title in administrative panel that leads to the Twitter bridge configuration.
                _m('Twitter bridge configuration page.'),
                $action_name == 'twitteradminpanel',
                'nav_twitter_admin_panel'
            );
        }

        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version blocks
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name' => 'TwitterBridge',
            'version' => self::VERSION,
            'author' => 'Zach Copley, Julien C, Jean Baptiste Favre',
            'homepage' => 'http://status.net/wiki/Plugin:TwitterBridge',
            // TRANS: Plugin description.
            'rawdescription' => _m('The Twitter "bridge" plugin allows integration ' .
                'of a StatusNet instance with ' .
                '<a href="http://twitter.com/">Twitter</a>.'
            )
        );
        return true;
    }

    /**
     * Expose the adminImportControl setting to the administration panel code.
     * This allows us to disable the import bridge enabling checkbox for administrators,
     * since on a bulk farm site we can't yet automate the import daemon setup.
     *
     * @return boolean hook value;
     */
    function onTwitterBridgeAdminImportControl()
    {
        return (bool)$this->adminImportControl;
    }

    /**
     * When the site is set to ssl=sometimes mode, we should make sure our
     * various auth-related pages are on SSL to keep things looking happy.
     * Although we're not submitting passwords directly, we do link out to
     * an authentication source and it's a lot happier if we've got some
     * protection against MitM.
     *
     * @param string $action name
     * @param boolean $ssl outval to force SSL
     * @return mixed hook return value
     */
    function onSensitiveAction($action, &$ssl)
    {
        $sensitive = array('twitteradminpanel',
                           'twittersettings',
                           'twitterauthorization',
                           'twitterlogin');
        if (in_array($action, $sensitive)) {
            $ssl = true;
            return false;
        } else {
            return true;
        }
    }

    /**
     * Database schema setup
     *
     * We maintain a table mapping StatusNet notices to Twitter statuses
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For saving the last-synched status of various timelines
        // home_timeline, messages (in), messages (out), ...

        $schema->ensureTable('twitter_synch_status',
                             array(new ColumnDef('foreign_id', 'bigint', null,
                                                 false, 'PRI'),
                                   new ColumnDef('timeline', 'varchar', 255,
                                                 false, 'PRI'),
                                   new ColumnDef('last_id', 'bigint', null, // XXX: check for PostgreSQL
                                                 false),
                                   new ColumnDef('created', 'datetime', null,
                                                 false),
                                   new ColumnDef('modified', 'datetime', null,
                                                 false)));

        // For storing user-submitted flags on profiles

        $schema->ensureTable('notice_to_status',
                             array(new ColumnDef('notice_id', 'integer', null,
                                                 false, 'PRI'),
                                   new ColumnDef('status_id', 'bigint', null, // XXX: check for PostgreSQL
                                                 false, 'UNI'),
                                   new ColumnDef('created', 'datetime', null,
                                                 false)));

        return true;
    }

    /**
     * If a notice gets deleted, remove the Notice_to_status mapping and
     * delete the status on Twitter.
     *
     * @param User   $user   The user doing the deleting
     * @param Notice $notice The notice getting deleted
     *
     * @return boolean hook value
     */
    function onStartDeleteOwnNotice(User $user, Notice $notice)
    {
        $n2s = Notice_to_status::staticGet('notice_id', $notice->id);

        if (!empty($n2s)) {

            $flink = Foreign_link::getByUserID($notice->profile_id,
                                               TWITTER_SERVICE); // twitter service

            if (empty($flink)) {
                return true;
            }

            if (!TwitterOAuthClient::isPackedToken($flink->credentials)) {
                $this->log(LOG_INFO, "Skipping deleting notice for {$notice->id} since link is not OAuth.");
                return true;
            }

            try {
                $token = TwitterOAuthClient::unpackToken($flink->credentials);
                $client = new TwitterOAuthClient($token->key, $token->secret);

                $client->statusesDestroy($n2s->status_id);
            } catch (Exception $e) {
                common_log(LOG_ERR, "Error attempting to delete bridged notice from Twitter: " . $e->getMessage());
            }

            $n2s->delete();
        }
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
        $flink = Foreign_link::getByUserID($profile->id,
                                           TWITTER_SERVICE); // twitter service

        if (empty($flink)) {
            return true;
        }

        if (!TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $this->log(LOG_INFO, "Skipping fave processing for {$profile->id} since link is not OAuth.");
            return true;
        }

        $status_id = twitter_status_id($notice);

        if (empty($status_id)) {
            return true;
        }

        try {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);

            $client->favoritesCreate($status_id);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Error attempting to favorite bridged notice on Twitter: " . $e->getMessage());
        }

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
        $flink = Foreign_link::getByUserID($profile->id,
                                           TWITTER_SERVICE); // twitter service

        if (empty($flink)) {
            return true;
        }

        if (!TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $this->log(LOG_INFO, "Skipping fave processing for {$profile->id} since link is not OAuth.");
            return true;
        }

        $status_id = twitter_status_id($notice);

        if (empty($status_id)) {
            return true;
        }

        try {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);

            $client->favoritesDestroy($status_id);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Error attempting to unfavorite bridged notice on Twitter: " . $e->getMessage());
        }

        return true;
    }

    function onStartGetProfileUri($profile, &$uri)
    {
        if (preg_match('!^https?://twitter.com/!', $profile->profileurl)) {
            $uri = $profile->profileurl;
            return false;
        }
        return true;
    }
}

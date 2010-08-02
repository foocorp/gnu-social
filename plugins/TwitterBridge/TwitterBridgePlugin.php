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
        $m->connect('admin/twitter', array('action' => 'twitteradminpanel'));

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
     * @param Action &action the current action
     *
     * @return void
     */
    function onEndLoginGroupNav(&$action)
    {
        $action_name = $action->trimmed('action');

        if (self::hasKeys() && common_config('twitter', 'signin')) {
            $action->menuItem(
                common_local_url('twitterlogin'),
                _m('Twitter'),
                _m('Login or register using Twitter'),
                'twitterlogin' === $action_name
            );
        }

        return true;
    }

    /**
     * Add the Twitter Settings page to the Connect Settings menu
     *
     * @param Action &$action The calling page
     *
     * @return boolean hook return
     */
    function onEndConnectSettingsNav(&$action)
    {
        if (self::hasKeys()) {
            $action_name = $action->trimmed('action');

            $action->menuItem(
                common_local_url('twittersettings'),
                _m('Twitter'),
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
        switch ($cls) {
        case 'TwittersettingsAction':
        case 'TwitterauthorizationAction':
        case 'TwitterloginAction':
        case 'TwitteradminpanelAction':
            include_once INSTALLDIR . '/plugins/TwitterBridge/' .
              strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'TwitterOAuthClient':
        case 'TwitterQueueHandler':
            include_once INSTALLDIR . '/plugins/TwitterBridge/' .
              strtolower($cls) . '.php';
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
        if (self::hasKeys() && $notice->isLocal()) {
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
    function onGetValidDaemons($daemons)
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
            $manager->connect('twitter', 'TwitterQueueHandler');
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
                _m('Twitter'),
                _m('Twitter bridge configuration'),
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
            'author' => 'Zach Copley, Julien C',
            'homepage' => 'http://status.net/wiki/Plugin:TwitterBridge',
            'rawdescription' => _m(
                'The Twitter "bridge" plugin allows you to integrate ' .
                'your StatusNet instance with ' .
                '<a href="http://twitter.com/">Twitter</a>.'
            )
        );
        return true;
    }

}


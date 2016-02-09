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

if (!defined('GNUSOCIAL')) { exit(1); }

require_once __DIR__ . '/twitter.php';

/**
 * Plugin for sending and importing Twitter statuses
 *
 * This class allows users to link their Twitter accounts
 *
 * Depends on Favorite plugin.
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
    const VERSION = GNUSOCIAL_VERSION;
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
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook return
     */
    public function onRouterInitialized(URLMapper $m)
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
    function onPluginVersion(array &$versions)
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
        $schema->ensureTable('twitter_synch_status', Twitter_synch_status::schemaDef());

        // For storing user-submitted flags on profiles
        $schema->ensureTable('notice_to_status', Notice_to_status::schemaDef());

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
        $n2s = Notice_to_status::getKV('notice_id', $notice->id);

        if ($n2s instanceof Notice_to_status) {

            try {
                $flink = Foreign_link::getByUserID($notice->profile_id, TWITTER_SERVICE); // twitter service
            } catch (NoResultException $e) {
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
        try {
            $flink = Foreign_link::getByUserID($profile->getID(), TWITTER_SERVICE); // twitter service
        } catch (NoResultException $e) {
            return true;
        }

        if (!TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $this->log(LOG_INFO, "Skipping fave processing for {$profile->getID()} since link is not OAuth.");
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
        try {
            $flink = Foreign_link::getByUserID($profile->getID(), TWITTER_SERVICE); // twitter service
        } catch (NoResultException $e) {
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

    /**
     * Add links in the user's profile block to their Twitter profile URL.
     *
     * @param Profile $profile The profile being shown
     * @param Array   &$links  Writeable array of arrays (href, text, image).
     *
     * @return boolean hook value (true)
     */

    function onOtherAccountProfiles($profile, &$links)
    {
        $fuser = null;

        try {
            $flink = Foreign_link::getByUserID($profile->id, TWITTER_SERVICE);
            $fuser = $flink->getForeignUser();

            $links[] = array("href" => $fuser->uri,
                             "text" => sprintf(_("@%s on Twitter"), $fuser->nickname),
                             "image" => $this->path("icons/twitter-bird-white-on-blue.png"));
        } catch (NoResultException $e) {
            // no foreign link and/or user for Twitter on this profile ID
        }

        return true;
    }

    public function onEndShowHeadElements(Action $action)
    {
        if($action instanceof ShowNoticeAction) { // Showing a notice
            $notice = Notice::getKV('id', $action->arg('notice'));

            try {
                $flink = Foreign_link::getByUserID($notice->profile_id, TWITTER_SERVICE);
                $fuser = Foreign_user::getForeignUser($flink->foreign_id, TWITTER_SERVICE);
            } catch (NoResultException $e) {
                return true;
            }

            $statusId = twitter_status_id($notice);
            if($notice instanceof Notice && $notice->isLocal() && $statusId) {
                $tweetUrl = 'https://twitter.com/' . $fuser->nickname . '/status/' . $statusId;
                $action->element('link', array('rel' => 'syndication', 'href' => $tweetUrl));
            }
        }

        if (!($action instanceof AttachmentAction)) {
            return true;
        }

        /* Twitter card support. See https://dev.twitter.com/docs/cards */
        /* @fixme: should we display twitter cards only for attachments posted
         *         by local users ? Seems mandatory to display twitter:creator
         *
         * Author: jbfavre
         */
        switch ($action->attachment->mimetype) {
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
            case 'image/png':
            case 'image/gif':
                $action->element('meta', array('name'    => 'twitter:card',
                                             'content' => 'photo'),
                                       null);
                $action->element('meta', array('name'    => 'twitter:url',
                                             'content' => common_local_url('attachment',
                                                              array('attachment' => $action->attachment->id))),
                                       null );
                $action->element('meta', array('name'    => 'twitter:image',
                                             'content' => $action->attachment->url));
                $action->element('meta', array('name'    => 'twitter:title',
                                             'content' => $action->attachment->title));

                $ns = new AttachmentNoticeSection($action);
                $notices = $ns->getNotices();
                $noticeArray = $notices->fetchAll();

                // Should not have more than 1 notice for this attachment.
                if( count($noticeArray) != 1 ) { break; }
                $post = $noticeArray[0];

                try {
                    $flink = Foreign_link::getByUserID($post->profile_id, TWITTER_SERVICE);
                    $fuser = Foreign_user::getForeignUser($flink->foreign_id, TWITTER_SERVICE);
                    $action->element('meta', array('name'    => 'twitter:creator',
                                                   'content' => '@'.$fuser->nickname));
                } catch (NoResultException $e) {
                    // no foreign link and/or user for Twitter on this profile ID
                }
                break;
            default:
                break;
        }

        return true;
    }
}

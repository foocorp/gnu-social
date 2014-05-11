<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * URL routing utilities
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
 * @category  URL
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * URL Router
 *
 * Cheap wrapper around Net_URL_Mapper
 *
 * @category URL
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Router
{
    var $m = null;
    static $inst = null;

    const REGEX_TAG = '[^\/]+'; // [\pL\pN_\-\.]{1,64} better if we can do unicode regexes

    static function get()
    {
        if (!Router::$inst) {
            Router::$inst = new Router();
        }
        return Router::$inst;
    }

    /**
     * Clear the global singleton instance for this class.
     * Needed to ensure reset when switching site configurations.
     */
    static function clear()
    {
        Router::$inst = null;
    }

    function __construct()
    {
        if (empty($this->m)) {
            $this->m = $this->initialize();
        }
    }

    /**
     * Create a unique hashkey for the router.
     *
     * The router's url map can change based on the version of the software
     * you're running and the plugins that are enabled. To avoid having bad routes
     * get stuck in the cache, the key includes a list of plugins and the software
     * version.
     * 
    * There can still be problems with a) differences in versions of the plugins and
     * b) people running code between official versions, but these tend to be more
     * sophisticated users who can grok what's going on and clear their caches.
     *
     * @return string cache key string that should uniquely identify a router
     */

    static function cacheKey()
    {
        $parts = array('router');

        // Many router paths depend on this setting.
        if (common_config('singleuser', 'enabled')) {
            $parts[] = '1user';
        } else {
            $parts[] = 'multi';
        }

        return Cache::codeKey(implode(':', $parts));
    }

    function initialize()
    {
        $m = new URLMapper();

        if (Event::handle('StartInitializeRouter', array(&$m))) {

            $m->connect('robots.txt', array('action' => 'robotstxt'));

            $m->connect('opensearch/people', array('action' => 'opensearch',
                                                   'type' => 'people'));
            $m->connect('opensearch/notice', array('action' => 'opensearch',
                                                   'type' => 'notice'));

            // docs

            $m->connect('doc/:title', array('action' => 'doc'));

            $m->connect('main/otp/:user_id/:token',
                        array('action' => 'otp'),
                        array('user_id' => '[0-9]+',
                              'token' => '.+'));

            // these take a code; before the main part

            foreach (array('register', 'confirmaddress', 'recoverpassword') as $c) {
                $m->connect('main/'.$c.'/:code', array('action' => $c));
            }

            // Also need a block variant accepting ID on URL for mail links
            $m->connect('main/block/:profileid',
                        array('action' => 'block'),
                        array('profileid' => '[0-9]+'));

            $m->connect('main/sup/:seconds', array('action' => 'sup'),
                        array('seconds' => '[0-9]+'));

            // main stuff is repetitive

            $main = array('login', 'logout', 'register', 'subscribe',
                          'unsubscribe', 'cancelsubscription', 'approvesub',
                          'confirmaddress', 'recoverpassword',
                          'invite', 'favor', 'disfavor', 'sup',
                          'block', 'unblock', 'subedit',
                          'groupblock', 'groupunblock',
                          'sandbox', 'unsandbox',
                          'silence', 'unsilence',
                          'grantrole', 'revokerole',
                          'repeat',
                          'deleteuser',
                          'geocode',
                          'version',
                          'backupaccount',
                          'deleteaccount',
                          'restoreaccount',
                          'top',
            );

            foreach ($main as $a) {
                $m->connect('main/'.$a, array('action' => $a));
            }

            $m->connect('main/tagprofile/:id', array('action' => 'tagprofile'),
                                               array('id' => '[0-9]+'));

            $m->connect('main/tagprofile', array('action' => 'tagprofile'));

            $m->connect('main/xrds',
                        array('action' => 'publicxrds'));

            // settings

            foreach (array('profile', 'avatar', 'password', 'im', 'oauthconnections',
                           'oauthapps', 'email', 'sms', 'url') as $s) {
                $m->connect('settings/'.$s, array('action' => $s.'settings'));
            }

            if (common_config('oldschool', 'enabled')) {
                $m->connect('settings/oldschool', array('action' => 'oldschoolsettings'));
            }

            $m->connect('settings/oauthapps/show/:id',
                        array('action' => 'showapplication'),
                        array('id' => '[0-9]+')
            );
            $m->connect('settings/oauthapps/new',
                        array('action' => 'newapplication')
            );
            $m->connect('settings/oauthapps/edit/:id',
                        array('action' => 'editapplication'),
                        array('id' => '[0-9]+')
            );
            $m->connect('settings/oauthapps/delete/:id',
                        array('action' => 'deleteapplication'),
                        array('id' => '[0-9]+')
            );

            // search

            foreach (array('group', 'people', 'notice') as $s) {
                $m->connect('search/'.$s.'?q=:q',
                            array('action' => $s.'search'),
                            array('q' => '.+'));
                $m->connect('search/'.$s, array('action' => $s.'search'));
            }

            // The second of these is needed to make the link work correctly
            // when inserted into the page. The first is needed to match the
            // route on the way in. Seems to be another Net_URL_Mapper bug to me.
            $m->connect('search/notice/rss?q=:q', array('action' => 'noticesearchrss'),
                        array('q' => '.+'));
            $m->connect('search/notice/rss', array('action' => 'noticesearchrss'));

            $m->connect('attachment/:attachment',
                        array('action' => 'attachment'),
                        array('attachment' => '[0-9]+'));

            $m->connect('attachment/:attachment/ajax',
                        array('action' => 'attachment_ajax'),
                        array('attachment' => '[0-9]+'));

            $m->connect('attachment/:attachment/thumbnail',
                        array('action' => 'attachment_thumbnail'),
                        array('attachment' => '[0-9]+'));

            $m->connect('notice/new?replyto=:replyto&inreplyto=:inreplyto',
                        array('action' => 'newnotice'),
                        array('replyto' => Nickname::DISPLAY_FMT),
                        array('inreplyto' => '[0-9]+'));

            $m->connect('notice/new?replyto=:replyto',
                        array('action' => 'newnotice'),
                        array('replyto' => Nickname::DISPLAY_FMT));

            $m->connect('notice/new', array('action' => 'newnotice'));

            $m->connect('notice/:notice',
                        array('action' => 'shownotice'),
                        array('notice' => '[0-9]+'));

            $m->connect('notice/delete/:notice',
                        array('action' => 'deletenotice'),
                        array('notice' => '[0-9]+'));

            $m->connect('notice/delete', array('action' => 'deletenotice'));

            // conversation

            $m->connect('conversation/:id',
                        array('action' => 'conversation'),
                        array('id' => '[0-9]+'));

            $m->connect('message/new', array('action' => 'newmessage'));
            $m->connect('message/new?to=:to', array('action' => 'newmessage'), array('to' => Nickname::DISPLAY_FMT));
            $m->connect('message/:message',
                        array('action' => 'showmessage'),
                        array('message' => '[0-9]+'));

            $m->connect('user/:id',
                        array('action' => 'userbyid'),
                        array('id' => '[0-9]+'));

            if (!common_config('performance', 'high')) {
                $m->connect('tags/', array('action' => 'publictagcloud'));
                $m->connect('tag/', array('action' => 'publictagcloud'));
                $m->connect('tags', array('action' => 'publictagcloud'));
                $m->connect('tag', array('action' => 'publictagcloud'));
            }
            $m->connect('tag/:tag/rss',
                        array('action' => 'tagrss'),
                        array('tag' => self::REGEX_TAG));
            $m->connect('tag/:tag',
                        array('action' => 'tag'),
                        array('tag' => self::REGEX_TAG));

            // groups

            $m->connect('group/new', array('action' => 'newgroup'));

            foreach (array('edit', 'join', 'leave', 'delete', 'cancel', 'approve') as $v) {
                $m->connect('group/:nickname/'.$v,
                            array('action' => $v.'group'),
                            array('nickname' => Nickname::DISPLAY_FMT));
                $m->connect('group/:id/id/'.$v,
                            array('action' => $v.'group'),
                            array('id' => '[0-9]+'));
            }

            foreach (array('members', 'logo', 'rss') as $n) {
                $m->connect('group/:nickname/'.$n,
                            array('action' => 'group'.$n),
                            array('nickname' => Nickname::DISPLAY_FMT));
            }

            $m->connect('group/:nickname/foaf',
                        array('action' => 'foafgroup'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/:nickname/blocked',
                        array('action' => 'blockedfromgroup'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/:nickname/makeadmin',
                        array('action' => 'makeadmin'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/:nickname/members/pending',
                        array('action' => 'groupqueue'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/:id/id',
                        array('action' => 'groupbyid'),
                        array('id' => '[0-9]+'));

            $m->connect('group/:nickname',
                        array('action' => 'showgroup'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/:nickname/',
                        array('action' => 'showgroup'),
                        array('nickname' => Nickname::DISPLAY_FMT));

            $m->connect('group/', array('action' => 'groups'));
            $m->connect('group', array('action' => 'groups'));
            $m->connect('groups/', array('action' => 'groups'));
            $m->connect('groups', array('action' => 'groups'));

            // Twitter-compatible API

            // statuses API

            $m->connect('api',
                        array('action' => 'Redirect',
                              'nextAction' => 'doc',
                              'args' => array('title' => 'api')));

            $m->connect('api/statuses/public_timeline.:format',
                        array('action' => 'ApiTimelinePublic',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/friends_timeline/:id.:format',
                        array('action' => 'ApiTimelineFriends',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/friends_timeline.:format',
                        array('action' => 'ApiTimelineFriends',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/home_timeline/:id.:format',
                        array('action' => 'ApiTimelineHome',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/home_timeline.:format',
                        array('action' => 'ApiTimelineHome',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/user_timeline/:id.:format',
                        array('action' => 'ApiTimelineUser',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/user_timeline.:format',
                        array('action' => 'ApiTimelineUser',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/mentions/:id.:format',
                        array('action' => 'ApiTimelineMentions',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/mentions.:format',
                        array('action' => 'ApiTimelineMentions',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/replies/:id.:format',
                        array('action' => 'ApiTimelineMentions',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/replies.:format',
                        array('action' => 'ApiTimelineMentions',
                              'format' => '(xml|json|rss|atom|as)'));
 
            $m->connect('api/statuses/mentions_timeline/:id.:format',
                        array('action' => 'ApiTimelineMentions',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/mentions_timeline.:format',
                        array('action' => 'ApiTimelineMentions',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statuses/retweeted_by_me.:format',
                        array('action' => 'ApiTimelineRetweetedByMe',
                              'format' => '(xml|json|atom|as)'));

            $m->connect('api/statuses/retweeted_to_me.:format',
                        array('action' => 'ApiTimelineRetweetedToMe',
                              'format' => '(xml|json|atom|as)'));

            $m->connect('api/statuses/retweets_of_me.:format',
                        array('action' => 'ApiTimelineRetweetsOfMe',
                              'format' => '(xml|json|atom|as)'));

            $m->connect('api/statuses/friends/:id.:format',
                        array('action' => 'ApiUserFriends',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/friends.:format',
                        array('action' => 'ApiUserFriends',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/followers/:id.:format',
                        array('action' => 'ApiUserFollowers',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/followers.:format',
                        array('action' => 'ApiUserFollowers',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/show/:id.:format',
                        array('action' => 'ApiStatusesShow',
                              'id' => '[0-9]+',
                              'format' => '(xml|json|atom)'));

            $m->connect('api/statuses/show.:format',
                        array('action' => 'ApiStatusesShow',
                              'format' => '(xml|json|atom)'));

            $m->connect('api/statuses/update.:format',
                        array('action' => 'ApiStatusesUpdate',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/destroy/:id.:format',
                        array('action' => 'ApiStatusesDestroy',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/destroy.:format',
                        array('action' => 'ApiStatusesDestroy',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/retweet/:id.:format',
                        array('action' => 'ApiStatusesRetweet',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/retweets/:id.:format',
                        array('action' => 'ApiStatusesRetweets',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            // START qvitter API additions

            $m->connect('api/statuses/favs/:id.:format',
                        array('action' => 'ApiStatusesFavs',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));
            
            $m->connect('api/attachment/:id.:format',
                        array('action' => 'ApiAttachment',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));
            
            $m->connect('api/checkhub.:format',
                        array('action' => 'ApiCheckHub',
                              'format' => '(xml|json)'));
            
            $m->connect('api/externalprofile/show.:format',
                        array('action' => 'ApiExternalProfileShow',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/admins/:id.:format',
                        array('action' => 'ApiGroupAdmins',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));
            
            $m->connect('api/account/update_link_color.:format',
                        array('action' => 'ApiAccountUpdateLinkColor',
                              'format' => '(xml|json)'));
                
            $m->connect('api/account/update_background_color.:format',
                        array('action' => 'ApiAccountUpdateBackgroundColor',
                              'format' => '(xml|json)'));

            $m->connect('api/account/register.:format',
                        array('action' => 'ApiAccountRegister',
                              'format' => '(xml|json)'));
            
            $m->connect('api/check_nickname.:format',
                        array('action' => 'ApiCheckNickname',
                              'format' => '(xml|json)'));

            // END qvitter API additions

            // users

            $m->connect('api/users/show/:id.:format',
                        array('action' => 'ApiUserShow',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/users/show.:format',
                        array('action' => 'ApiUserShow',
                              'format' => '(xml|json)'));

            $m->connect('api/users/profile_image/:screen_name.:format',
                        array('action' => 'ApiUserProfileImage',
                              'screen_name' => Nickname::DISPLAY_FMT,
                              'format' => '(xml|json)'));

            // direct messages

            $m->connect('api/direct_messages.:format',
                        array('action' => 'ApiDirectMessage',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/direct_messages/sent.:format',
                        array('action' => 'ApiDirectMessage',
                              'format' => '(xml|json|rss|atom)',
                              'sent' => true));

            $m->connect('api/direct_messages/new.:format',
                        array('action' => 'ApiDirectMessageNew',
                              'format' => '(xml|json)'));

            // friendships

            $m->connect('api/friendships/show.:format',
                        array('action' => 'ApiFriendshipsShow',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/exists.:format',
                        array('action' => 'ApiFriendshipsExists',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/create/:id.:format',
                        array('action' => 'ApiFriendshipsCreate',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/create.:format',
                        array('action' => 'ApiFriendshipsCreate',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/destroy/:id.:format',
                        array('action' => 'ApiFriendshipsDestroy',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/destroy.:format',
                        array('action' => 'ApiFriendshipsDestroy',
                              'format' => '(xml|json)'));

            // Social graph

            $m->connect('api/friends/ids/:id.:format',
                        array('action' => 'ApiUserFriends',
                              'ids_only' => true));

            $m->connect('api/followers/ids/:id.:format',
                        array('action' => 'ApiUserFollowers',
                              'ids_only' => true));

            $m->connect('api/friends/ids.:format',
                        array('action' => 'ApiUserFriends',
                              'ids_only' => true));

            $m->connect('api/followers/ids.:format',
                        array('action' => 'ApiUserFollowers',
                              'ids_only' => true));

            // account

            $m->connect('api/account/verify_credentials.:format',
                        array('action' => 'ApiAccountVerifyCredentials'));

            $m->connect('api/account/update_profile.:format',
                        array('action' => 'ApiAccountUpdateProfile'));

            $m->connect('api/account/update_profile_image.:format',
                        array('action' => 'ApiAccountUpdateProfileImage'));

            $m->connect('api/account/update_delivery_device.:format',
                        array('action' => 'ApiAccountUpdateDeliveryDevice'));

            // special case where verify_credentials is called w/out a format

            $m->connect('api/account/verify_credentials',
                        array('action' => 'ApiAccountVerifyCredentials'));

            $m->connect('api/account/rate_limit_status.:format',
                        array('action' => 'ApiAccountRateLimitStatus'));

            // favorites

            $m->connect('api/favorites/create.:format',
                        array('action' => 'ApiFavoriteCreate',
                              'format' => '(xml|json)'));

            $m->connect('api/favorites/destroy.:format',
                        array('action' => 'ApiFavoriteDestroy',
                              'format' => '(xml|json)'));

            $m->connect('api/favorites/list.:format',
                        array('action' => 'ApiTimelineFavorites',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/favorites/:id.:format',
                        array('action' => 'ApiTimelineFavorites',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/favorites.:format',
                        array('action' => 'ApiTimelineFavorites',
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/favorites/create/:id.:format',
                        array('action' => 'ApiFavoriteCreate',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/favorites/destroy/:id.:format',
                        array('action' => 'ApiFavoriteDestroy',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            // blocks

            $m->connect('api/blocks/create/:id.:format',
                        array('action' => 'ApiBlockCreate',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/blocks/create.:format',
                        array('action' => 'ApiBlockCreate',
                              'format' => '(xml|json)'));

            $m->connect('api/blocks/destroy/:id.:format',
                        array('action' => 'ApiBlockDestroy',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/blocks/destroy.:format',
                        array('action' => 'ApiBlockDestroy',
                              'format' => '(xml|json)'));

            // help

            $m->connect('api/help/test.:format',
                        array('action' => 'ApiHelpTest',
                              'format' => '(xml|json)'));

            // statusnet

            $m->connect('api/statusnet/version.:format',
                        array('action' => 'ApiGNUsocialVersion',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/config.:format',
                        array('action' => 'ApiGNUsocialConfig',
                              'format' => '(xml|json)'));

            // For our current software name, we provide "gnusocial" base action

            $m->connect('api/gnusocial/version.:format',
                        array('action' => 'ApiGNUsocialVersion',
                              'format' => '(xml|json)'));

            $m->connect('api/gnusocial/config.:format',
                        array('action' => 'ApiGNUsocialConfig',
                              'format' => '(xml|json)'));

            // Groups and tags are newer than 0.8.1 so no backward-compatibility
            // necessary

            // Groups
            //'list' has to be handled differently, as php will not allow a method to be named 'list'

            $m->connect('api/statusnet/groups/timeline/:id.:format',
                        array('action' => 'ApiTimelineGroup',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom|as)'));

            $m->connect('api/statusnet/groups/show/:id.:format',
                        array('action' => 'ApiGroupShow',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/show.:format',
                        array('action' => 'ApiGroupShow',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/join/:id.:format',
                        array('action' => 'ApiGroupJoin',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/join.:format',
                        array('action' => 'ApiGroupJoin',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/leave/:id.:format',
                        array('action' => 'ApiGroupLeave',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/leave.:format',
                        array('action' => 'ApiGroupLeave',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/is_member.:format',
                        array('action' => 'ApiGroupIsMember',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/list/:id.:format',
                        array('action' => 'ApiGroupList',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/list.:format',
                        array('action' => 'ApiGroupList',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/list_all.:format',
                        array('action' => 'ApiGroupListAll',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/membership/:id.:format',
                        array('action' => 'ApiGroupMembership',
                              'id' => Nickname::INPUT_FMT,
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/membership.:format',
                        array('action' => 'ApiGroupMembership',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/create.:format',
                        array('action' => 'ApiGroupCreate',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/update/:id.:format',
                        array('action' => 'ApiGroupProfileUpdate',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));
                              
            $m->connect('api/statusnet/conversation/:id.:format',
                        array('action' => 'apiconversation',
                              'id' => '[0-9]+',
                              'format' => '(xml|json|rss|atom|as)'));

            // Lists (people tags)
            $m->connect('api/lists/list.:format',
                        array('action' => 'ApiListSubscriptions',
                              'format' => '(xml|json)'));

            $m->connect('api/lists/memberships.:format',
                        array('action' => 'ApiListMemberships',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/lists/memberships.:format',
                        array('action' => 'ApiListMemberships',
                              'user' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/lists/subscriptions.:format',
                        array('action' => 'ApiListSubscriptions',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/lists/subscriptions.:format',
                        array('action' => 'ApiListSubscriptions',
                              'user' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/lists.:format',
                        array('action' => 'ApiLists',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/lists/:id.:format',
                        array('action' => 'ApiList',
                              'user' => '[a-zA-Z0-9]+',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/lists.:format',
                        array('action' => 'ApiLists',
                              'user' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/lists/:id/statuses.:format',
                        array('action' => 'ApiTimelineList',
                              'user' => '[a-zA-Z0-9]+',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/:user/:list_id/members/:id.:format',
                        array('action' => 'ApiListMember',
                              'user' => '[a-zA-Z0-9]+',
                              'list_id' => '[a-zA-Z0-9]+',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/:list_id/members.:format',
                        array('action' => 'ApiListMembers',
                              'user' => '[a-zA-Z0-9]+',
                              'list_id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/:list_id/subscribers/:id.:format',
                        array('action' => 'ApiListSubscriber',
                              'user' => '[a-zA-Z0-9]+',
                              'list_id' => '[a-zA-Z0-9]+',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/:user/:list_id/subscribers.:format',
                        array('action' => 'ApiListSubscribers',
                              'user' => '[a-zA-Z0-9]+',
                              'list_id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            // Tags
            $m->connect('api/statusnet/tags/timeline/:tag.:format',
                        array('action' => 'ApiTimelineTag',
                              'tag'    => self::REGEX_TAG,
                              'format' => '(xml|json|rss|atom|as)'));

            // media related
            $m->connect(
                'api/statusnet/media/upload',
                array('action' => 'ApiMediaUpload')
            );
            $m->connect(
                'api/statuses/update_with_media.json',
                array('action' => 'ApiMediaUpload')
            );

            // search
            $m->connect('api/search.atom', array('action' => 'ApiSearchAtom'));
            $m->connect('api/search.json', array('action' => 'ApiSearchJSON'));
            $m->connect('api/trends.json', array('action' => 'ApiTrends'));

            $m->connect('api/oauth/request_token',
                        array('action' => 'ApiOAuthRequestToken'));

            $m->connect('api/oauth/access_token',
                        array('action' => 'ApiOAuthAccessToken'));

            $m->connect('api/oauth/authorize',
                        array('action' => 'ApiOAuthAuthorize'));

            // Admin

            $m->connect('panel/site', array('action' => 'siteadminpanel'));
            $m->connect('panel/user', array('action' => 'useradminpanel'));
            $m->connect('panel/access', array('action' => 'accessadminpanel'));
            $m->connect('panel/paths', array('action' => 'pathsadminpanel'));
            $m->connect('panel/sessions', array('action' => 'sessionsadminpanel'));
            $m->connect('panel/sitenotice', array('action' => 'sitenoticeadminpanel'));
            $m->connect('panel/license', array('action' => 'licenseadminpanel'));

            $m->connect('panel/plugins', array('action' => 'pluginsadminpanel'));
            $m->connect('panel/plugins/enable/:plugin',
                        array('action' => 'pluginenable'),
                        array('plugin' => '[A-Za-z0-9_]+'));
            $m->connect('panel/plugins/disable/:plugin',
                        array('action' => 'plugindisable'),
                        array('plugin' => '[A-Za-z0-9_]+'));

            $m->connect('getfile/:filename',
                        array('action' => 'getfile'),
                        array('filename' => '[A-Za-z0-9._-]+'));

            // Common people-tag stuff

            $m->connect('peopletag/:tag', array('action' => 'peopletag',
                                                'tag'    => self::REGEX_TAG));

            $m->connect('selftag/:tag', array('action' => 'selftag',
                                              'tag'    => self::REGEX_TAG));

            $m->connect('main/addpeopletag', array('action' => 'addpeopletag'));

            $m->connect('main/removepeopletag', array('action' => 'removepeopletag'));

            $m->connect('main/profilecompletion', array('action' => 'profilecompletion'));

            $m->connect('main/peopletagautocomplete', array('action' => 'peopletagautocomplete'));

            // In the "root"

            if (common_config('singleuser', 'enabled')) {

                $nickname = User::singleUserNickname();

                foreach (array('subscriptions', 'subscribers',
                               'all', 'foaf', 'replies',
                               'microsummary') as $a) {
                    $m->connect($a,
                                array('action' => $a,
                                      'nickname' => $nickname));
                }

                foreach (array('subscriptions', 'subscribers') as $a) {
                    $m->connect($a.'/:tag',
                                array('action' => $a,
                                      'nickname' => $nickname),
                                array('tag' => self::REGEX_TAG));
                }

                $m->connect('subscribers/pending',
                            array('action' => 'subqueue',
                                  'nickname' => $nickname));

                foreach (array('rss', 'groups') as $a) {
                    $m->connect($a,
                                array('action' => 'user'.$a,
                                      'nickname' => $nickname));
                }

                foreach (array('all', 'replies', 'favorites') as $a) {
                    $m->connect($a.'/rss',
                                array('action' => $a.'rss',
                                      'nickname' => $nickname));
                }

                $m->connect('favorites',
                            array('action' => 'showfavorites',
                                  'nickname' => $nickname));

                $m->connect('avatar',
                            array('action' => 'avatarbynickname',
                                  'nickname' => $nickname));
                $m->connect('avatar/:size',
                            array('action' => 'avatarbynickname',
                                  'nickname' => $nickname),
                            array('size' => '(|original|\d+)'));

                $m->connect('tag/:tag/rss',
                            array('action' => 'userrss',
                                  'nickname' => $nickname),
                            array('tag' => self::REGEX_TAG));

                $m->connect('tag/:tag',
                            array('action' => 'showstream',
                                  'nickname' => $nickname),
                            array('tag' => self::REGEX_TAG));

                $m->connect('rsd.xml',
                            array('action' => 'rsd',
                                  'nickname' => $nickname));

                $m->connect('',
                            array('action' => 'showstream',
                                  'nickname' => $nickname));

                // peopletags

                $m->connect('peopletags',
                            array('action' => 'peopletagsbyuser'));

                $m->connect('peopletags/private',
                            array('action' => 'peopletagsbyuser',
                                  'private' => 1));

                $m->connect('peopletags/public',
                            array('action' => 'peopletagsbyuser',
                                  'public' => 1));

                $m->connect('othertags',
                            array('action' => 'peopletagsforuser'));

                $m->connect('peopletagsubscriptions',
                            array('action' => 'peopletagsubscriptions'));

                $m->connect('all/:tag/subscribers',
                            array('action' => 'peopletagsubscribers',
                                  'tag' => self::REGEX_TAG));

                $m->connect('all/:tag/tagged',
                                array('action' => 'peopletagged',
                                      'tag' => self::REGEX_TAG));

                $m->connect('all/:tag/edit',
                                array('action' => 'editpeopletag',
                                      'tag' => self::REGEX_TAG));

                foreach(array('subscribe', 'unsubscribe') as $v) {
                    $m->connect('peopletag/:id/'.$v,
                                    array('action' => $v.'peopletag',
                                          'id' => '[0-9]{1,64}'));
                }
                $m->connect('user/:tagger_id/profiletag/:id/id',
                                array('action' => 'profiletagbyid',
                                      'tagger_id' => '[0-9]+',
                                      'id' => '[0-9]+'));

                $m->connect('all/:tag',
                                array('action' => 'showprofiletag',
                                      'tag' => self::REGEX_TAG));

                foreach (array('subscriptions', 'subscribers') as $a) {
                    $m->connect($a.'/:tag',
                                array('action' => $a),
                                array('tag' => self::REGEX_TAG));
                }
            } else {
                $m->connect('', array('action' => 'public'));
                $m->connect('rss', array('action' => 'publicrss'));
                $m->connect('featuredrss', array('action' => 'featuredrss'));
                $m->connect('favoritedrss', array('action' => 'favoritedrss'));
                $m->connect('featured/', array('action' => 'featured'));
                $m->connect('featured', array('action' => 'featured'));
                $m->connect('favorited/', array('action' => 'favorited'));
                $m->connect('favorited', array('action' => 'favorited'));
                $m->connect('rsd.xml', array('action' => 'rsd'));

                foreach (array('subscriptions', 'subscribers',
                               'nudge', 'all', 'foaf', 'replies',
                               'inbox', 'outbox', 'microsummary') as $a) {
                    $m->connect(':nickname/'.$a,
                                array('action' => $a),
                                array('nickname' => Nickname::DISPLAY_FMT));
                }
                $m->connect(':nickname/subscribers/pending',
                            array('action' => 'subqueue'),
                            array('nickname' => Nickname::DISPLAY_FMT));

                // people tags

                $m->connect(':nickname/peopletags',
                                array('action' => 'peopletagsbyuser',
                                      'nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname/peopletags/private',
                                array('action' => 'peopletagsbyuser',
                                      'nickname' => Nickname::DISPLAY_FMT,
                                      'private' => 1));

                $m->connect(':nickname/peopletags/public',
                                array('action' => 'peopletagsbyuser',
                                      'nickname' => Nickname::DISPLAY_FMT,
                                      'public' => 1));

                $m->connect(':nickname/othertags',
                                array('action' => 'peopletagsforuser',
                                      'nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname/peopletagsubscriptions',
                                array('action' => 'peopletagsubscriptions',
                                      'nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':tagger/all/:tag/subscribers',
                                array('action' => 'peopletagsubscribers',
                                      'tagger' => Nickname::DISPLAY_FMT,
                                      'tag' => self::REGEX_TAG));

                $m->connect(':tagger/all/:tag/tagged',
                                array('action' => 'peopletagged',
                                      'tagger' => Nickname::DISPLAY_FMT,
                                      'tag' => self::REGEX_TAG));

                $m->connect(':tagger/all/:tag/edit',
                                array('action' => 'editpeopletag',
                                      'tagger' => Nickname::DISPLAY_FMT,
                                      'tag' => self::REGEX_TAG));

                foreach(array('subscribe', 'unsubscribe') as $v) {
                    $m->connect('peopletag/:id/'.$v,
                                    array('action' => $v.'peopletag',
                                          'id' => '[0-9]{1,64}'));
                }
                $m->connect('user/:tagger_id/profiletag/:id/id',
                                array('action' => 'profiletagbyid',
                                      'tagger_id' => '[0-9]+',
                                      'id' => '[0-9]+'));

                $m->connect(':tagger/all/:tag',
                                array('action' => 'showprofiletag',
                                      'tagger' => Nickname::DISPLAY_FMT,
                                      'tag' => self::REGEX_TAG));

                foreach (array('subscriptions', 'subscribers') as $a) {
                    $m->connect(':nickname/'.$a.'/:tag',
                                array('action' => $a),
                                array('tag' => self::REGEX_TAG,
                                      'nickname' => Nickname::DISPLAY_FMT));
                }

                foreach (array('rss', 'groups') as $a) {
                    $m->connect(':nickname/'.$a,
                                array('action' => 'user'.$a),
                                array('nickname' => Nickname::DISPLAY_FMT));
                }

                foreach (array('all', 'replies', 'favorites') as $a) {
                    $m->connect(':nickname/'.$a.'/rss',
                                array('action' => $a.'rss'),
                                array('nickname' => Nickname::DISPLAY_FMT));
                }

                $m->connect(':nickname/favorites',
                            array('action' => 'showfavorites'),
                            array('nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname/avatar',
                            array('action' => 'avatarbynickname'),
                            array('nickname' => Nickname::DISPLAY_FMT));
                $m->connect(':nickname/avatar/:size',
                            array('action' => 'avatarbynickname'),
                            array('size' => '(|original|\d+)',
                                  'nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname/tag/:tag/rss',
                            array('action' => 'userrss'),
                            array('nickname' => Nickname::DISPLAY_FMT),
                            array('tag' => self::REGEX_TAG));

                $m->connect(':nickname/tag/:tag',
                            array('action' => 'showstream'),
                            array('nickname' => Nickname::DISPLAY_FMT),
                            array('tag' => self::REGEX_TAG));

                $m->connect(':nickname/rsd.xml',
                            array('action' => 'rsd'),
                            array('nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname',
                            array('action' => 'showstream'),
                            array('nickname' => Nickname::DISPLAY_FMT));

                $m->connect(':nickname/',
                            array('action' => 'showstream'),
                            array('nickname' => Nickname::DISPLAY_FMT));
            }

            // AtomPub API

            $m->connect('api/statusnet/app/service/:id.xml',
                        array('action' => 'ApiAtomService'),
                        array('id' => Nickname::DISPLAY_FMT));

            $m->connect('api/statusnet/app/service.xml',
                        array('action' => 'ApiAtomService'));

            $m->connect('api/statusnet/app/subscriptions/:subscriber/:subscribed.atom',
                        array('action' => 'AtomPubShowSubscription'),
                        array('subscriber' => '[0-9]+',
                              'subscribed' => '[0-9]+'));

            $m->connect('api/statusnet/app/subscriptions/:subscriber.atom',
                        array('action' => 'AtomPubSubscriptionFeed'),
                        array('subscriber' => '[0-9]+'));

            $m->connect('api/statusnet/app/favorites/:profile/:notice.atom',
                        array('action' => 'AtomPubShowFavorite'),
                        array('profile' => '[0-9]+',
                              'notice' => '[0-9]+'));

            $m->connect('api/statusnet/app/favorites/:profile.atom',
                        array('action' => 'AtomPubFavoriteFeed'),
                        array('profile' => '[0-9]+'));

            $m->connect('api/statusnet/app/memberships/:profile/:group.atom',
                        array('action' => 'AtomPubShowMembership'),
                        array('profile' => '[0-9]+',
                              'group' => '[0-9]+'));

            $m->connect('api/statusnet/app/memberships/:profile.atom',
                        array('action' => 'AtomPubMembershipFeed'),
                        array('profile' => '[0-9]+'));

            // URL shortening

            $m->connect('url/:id',
                        array('action' => 'redirecturl',
                              'id' => '[0-9]+'));

            // user stuff

            Event::handle('RouterInitialized', array($m));
        }

        return $m;
    }

    function map($path)
    {
        try {
            $match = $this->m->match($path);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Problem getting route for $path - " .
                       $e->getMessage());
            // TRANS: Client error on action trying to visit a non-existing page.
            $cac = new ClientErrorAction(_('Page not found.'), 404);
            $cac->showPage();
        }

        return $match;
    }

    function build($action, $args=null, $params=null, $fragment=null)
    {
        $action_arg = array('action' => $action);

        if ($args) {
            $args = array_merge($action_arg, $args);
        } else {
            $args = $action_arg;
        }

        $url = $this->m->generate($args, $params, $fragment);
        // Due to a bug in the Net_URL_Mapper code, the returned URL may
        // contain a malformed query of the form ?p1=v1?p2=v2?p3=v3. We
        // repair that here rather than modifying the upstream code...

        $qpos = strpos($url, '?');
        if ($qpos !== false) {
            $url = substr($url, 0, $qpos+1) .
                str_replace('?', '&', substr($url, $qpos+1));

            // @fixme this is a hacky workaround for http_build_query in the
            // lower-level code and bad configs that set the default separator
            // to &amp; instead of &. Encoded &s in parameters will not be
            // affected.
            $url = substr($url, 0, $qpos+1) .
                str_replace('&amp;', '&', substr($url, $qpos+1));

        }

        return $url;
    }
}

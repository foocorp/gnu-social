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

require_once 'Net/URL/Mapper.php';

class StatusNet_URL_Mapper extends Net_URL_Mapper {

    private static $_singleton = null;

    private function __construct()
    {
    }

    public static function getInstance($id = '__default__')
    {
        if (empty(self::$_singleton)) {
            self::$_singleton = new StatusNet_URL_Mapper();
        }
        return self::$_singleton;
    }

    public function connect($path, $defaults = array(), $rules = array())
    {
        $result = null;
        if (Event::handle('StartConnectPath', array(&$path, &$defaults, &$rules, &$result))) {
            $result = parent::connect($path, $defaults, $rules);
            Event::handle('EndConnectPath', array($path, $defaults, $rules, $result));
        }
        return $result;
    }
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
    static $bare = array('requesttoken', 'accesstoken', 'userauthorization',
                         'postnotice', 'updateprofile', 'finishremotesubscribe');

    static function get()
    {
        if (!Router::$inst) {
            Router::$inst = new Router();
        }
        return Router::$inst;
    }

    function __construct()
    {
        if (!$this->m) {
            $this->m = $this->initialize();
        }
    }

    function initialize()
    {
        $m = StatusNet_URL_Mapper::getInstance();

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

            // main stuff is repetitive

            $main = array('login', 'logout', 'register', 'subscribe',
                          'unsubscribe', 'confirmaddress', 'recoverpassword',
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
                          );

            foreach ($main as $a) {
                $m->connect('main/'.$a, array('action' => $a));
            }

            // Also need a block variant accepting ID on URL for mail links
            $m->connect('main/block/:profileid',
                        array('action' => 'block'),
                        array('profileid' => '[0-9]+'));

            $m->connect('main/sup/:seconds', array('action' => 'sup'),
                        array('seconds' => '[0-9]+'));

            $m->connect('main/tagother/:id', array('action' => 'tagother'));

            $m->connect('main/oembed',
                        array('action' => 'oembed'));

            $m->connect('main/xrds',
                        array('action' => 'publicxrds'));

            // these take a code

            foreach (array('register', 'confirmaddress', 'recoverpassword') as $c) {
                $m->connect('main/'.$c.'/:code', array('action' => $c));
            }

            // exceptional

            $m->connect('main/remote', array('action' => 'remotesubscribe'));
            $m->connect('main/remote?nickname=:nickname', array('action' => 'remotesubscribe'), array('nickname' => '[A-Za-z0-9_-]+'));

            foreach (Router::$bare as $action) {
                $m->connect('index.php?action=' . $action, array('action' => $action));
            }

            // settings

            foreach (array('profile', 'avatar', 'password', 'im', 'oauthconnections',
                           'oauthapps', 'email', 'sms', 'userdesign', 'other') as $s) {
                $m->connect('settings/'.$s, array('action' => $s.'settings'));
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
                $m->connect('search/'.$s, array('action' => $s.'search'));
                $m->connect('search/'.$s.'?q=:q',
                            array('action' => $s.'search'),
                            array('q' => '.+'));
            }

            // The second of these is needed to make the link work correctly
            // when inserted into the page. The first is needed to match the
            // route on the way in. Seems to be another Net_URL_Mapper bug to me.
            $m->connect('search/notice/rss', array('action' => 'noticesearchrss'));
            $m->connect('search/notice/rss?q=:q', array('action' => 'noticesearchrss'),
                        array('q' => '.+'));

            $m->connect('attachment/:attachment',
                        array('action' => 'attachment'),
                        array('attachment' => '[0-9]+'));

            $m->connect('attachment/:attachment/ajax',
                        array('action' => 'attachment_ajax'),
                        array('attachment' => '[0-9]+'));

            $m->connect('attachment/:attachment/thumbnail',
                        array('action' => 'attachment_thumbnail'),
                        array('attachment' => '[0-9]+'));

            $m->connect('notice/new', array('action' => 'newnotice'));
            $m->connect('notice/new?replyto=:replyto',
                        array('action' => 'newnotice'),
                        array('replyto' => '[A-Za-z0-9_-]+'));
            $m->connect('notice/new?replyto=:replyto&inreplyto=:inreplyto',
                        array('action' => 'newnotice'),
                        array('replyto' => '[A-Za-z0-9_-]+'),
                        array('inreplyto' => '[0-9]+'));

            $m->connect('notice/:notice/file',
                        array('action' => 'file'),
                        array('notice' => '[0-9]+'));

            $m->connect('notice/:notice',
                        array('action' => 'shownotice'),
                        array('notice' => '[0-9]+'));
            $m->connect('notice/delete', array('action' => 'deletenotice'));
            $m->connect('notice/delete/:notice',
                        array('action' => 'deletenotice'),
                        array('notice' => '[0-9]+'));

            $m->connect('bookmarklet/new', array('action' => 'bookmarklet'));

            // conversation

            $m->connect('conversation/:id',
                        array('action' => 'conversation'),
                        array('id' => '[0-9]+'));

            $m->connect('message/new', array('action' => 'newmessage'));
            $m->connect('message/new?to=:to', array('action' => 'newmessage'), array('to' => '[A-Za-z0-9_-]+'));
            $m->connect('message/:message',
                        array('action' => 'showmessage'),
                        array('message' => '[0-9]+'));

            $m->connect('user/:id',
                        array('action' => 'userbyid'),
                        array('id' => '[0-9]+'));

            $m->connect('tags/', array('action' => 'publictagcloud'));
            $m->connect('tag/', array('action' => 'publictagcloud'));
            $m->connect('tags', array('action' => 'publictagcloud'));
            $m->connect('tag', array('action' => 'publictagcloud'));
            $m->connect('tag/:tag/rss',
                        array('action' => 'tagrss'),
                        array('tag' => '[\pL\pN_\-\.]{1,64}'));
            $m->connect('tag/:tag',
                        array('action' => 'tag'),
                        array('tag' => '[\pL\pN_\-\.]{1,64}'));

            $m->connect('peopletag/:tag',
                        array('action' => 'peopletag'),
                        array('tag' => '[a-zA-Z0-9]+'));

            // groups

            $m->connect('group/new', array('action' => 'newgroup'));

            foreach (array('edit', 'join', 'leave') as $v) {
                $m->connect('group/:nickname/'.$v,
                            array('action' => $v.'group'),
                            array('nickname' => '[a-zA-Z0-9]+'));
                $m->connect('group/:id/id/'.$v,
                            array('action' => $v.'group'),
                            array('id' => '[0-9]+'));
            }

            foreach (array('members', 'logo', 'rss', 'designsettings') as $n) {
                $m->connect('group/:nickname/'.$n,
                            array('action' => 'group'.$n),
                            array('nickname' => '[a-zA-Z0-9]+'));
            }

            $m->connect('group/:nickname/foaf',
                        array('action' => 'foafgroup'),
                        array('nickname' => '[a-zA-Z0-9]+'));

            $m->connect('group/:nickname/blocked',
                        array('action' => 'blockedfromgroup'),
                        array('nickname' => '[a-zA-Z0-9]+'));

            $m->connect('group/:nickname/makeadmin',
                        array('action' => 'makeadmin'),
                        array('nickname' => '[a-zA-Z0-9]+'));

            $m->connect('group/:id/id',
                        array('action' => 'groupbyid'),
                        array('id' => '[0-9]+'));

            $m->connect('group/:nickname',
                        array('action' => 'showgroup'),
                        array('nickname' => '[a-zA-Z0-9]+'));

            $m->connect('group/', array('action' => 'groups'));
            $m->connect('group', array('action' => 'groups'));
            $m->connect('groups/', array('action' => 'groups'));
            $m->connect('groups', array('action' => 'groups'));

            // Twitter-compatible API

            // statuses API

            $m->connect('api/statuses/public_timeline.:format',
                        array('action' => 'ApiTimelinePublic',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/friends_timeline.:format',
                        array('action' => 'ApiTimelineFriends',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/friends_timeline/:id.:format',
                        array('action' => 'ApiTimelineFriends',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/home_timeline.:format',
                        array('action' => 'ApiTimelineHome',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/home_timeline/:id.:format',
                        array('action' => 'ApiTimelineHome',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/user_timeline.:format',
                        array('action' => 'ApiTimelineUser',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/user_timeline/:id.:format',
                        array('action' => 'ApiTimelineUser',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/mentions.:format',
                        array('action' => 'ApiTimelineMentions',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/mentions/:id.:format',
                        array('action' => 'ApiTimelineMentions',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/replies.:format',
                        array('action' => 'ApiTimelineMentions',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/replies/:id.:format',
                        array('action' => 'ApiTimelineMentions',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statuses/retweeted_by_me.:format',
                        array('action' => 'ApiTimelineRetweetedByMe',
                              'format' => '(xml|json|atom)'));

            $m->connect('api/statuses/retweeted_to_me.:format',
                        array('action' => 'ApiTimelineRetweetedToMe',
                              'format' => '(xml|json|atom)'));

            $m->connect('api/statuses/retweets_of_me.:format',
                        array('action' => 'ApiTimelineRetweetsOfMe',
                              'format' => '(xml|json|atom)'));

            $m->connect('api/statuses/friends.:format',
                        array('action' => 'ApiUserFriends',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/friends/:id.:format',
                        array('action' => 'ApiUserFriends',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/followers.:format',
                        array('action' => 'ApiUserFollowers',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/followers/:id.:format',
                        array('action' => 'ApiUserFollowers',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/show.:format',
                        array('action' => 'ApiStatusesShow',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/show/:id.:format',
                        array('action' => 'ApiStatusesShow',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/update.:format',
                        array('action' => 'ApiStatusesUpdate',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/destroy.:format',
                        array('action' => 'ApiStatusesDestroy',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/destroy/:id.:format',
                        array('action' => 'ApiStatusesDestroy',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/retweet/:id.:format',
                        array('action' => 'ApiStatusesRetweet',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statuses/retweets/:id.:format',
                        array('action' => 'ApiStatusesRetweets',
                              'id' => '[0-9]+',
                              'format' => '(xml|json)'));

            // users

            $m->connect('api/users/show.:format',
                        array('action' => 'ApiUserShow',
                              'format' => '(xml|json)'));

            $m->connect('api/users/show/:id.:format',
                        array('action' => 'ApiUserShow',
                              'id' => '[a-zA-Z0-9]+',
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

            $m->connect('api/friendships/create.:format',
                        array('action' => 'ApiFriendshipsCreate',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/destroy.:format',
                        array('action' => 'ApiFriendshipsDestroy',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/create/:id.:format',
                        array('action' => 'ApiFriendshipsCreate',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/friendships/destroy/:id.:format',
                        array('action' => 'ApiFriendshipsDestroy',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            // Social graph

            $m->connect('api/friends/ids/:id.:format',
                        array('action' => 'apiuserfriends',
                              'ids_only' => true));

            $m->connect('api/followers/ids/:id.:format',
                        array('action' => 'apiuserfollowers',
                              'ids_only' => true));

            $m->connect('api/friends/ids.:format',
                        array('action' => 'apiuserfriends',
                              'ids_only' => true));

            $m->connect('api/followers/ids.:format',
                        array('action' => 'apiuserfollowers',
                              'ids_only' => true));

            // account

            $m->connect('api/account/verify_credentials.:format',
                        array('action' => 'ApiAccountVerifyCredentials'));

            $m->connect('api/account/update_profile.:format',
                        array('action' => 'ApiAccountUpdateProfile'));

            $m->connect('api/account/update_profile_image.:format',
                        array('action' => 'ApiAccountUpdateProfileImage'));

            $m->connect('api/account/update_profile_background_image.:format',
                        array('action' => 'ApiAccountUpdateProfileBackgroundImage'));

            $m->connect('api/account/update_profile_colors.:format',
                        array('action' => 'ApiAccountUpdateProfileColors'));

            $m->connect('api/account/update_delivery_device.:format',
                        array('action' => 'ApiAccountUpdateDeliveryDevice'));

            // special case where verify_credentials is called w/out a format

            $m->connect('api/account/verify_credentials',
                        array('action' => 'ApiAccountVerifyCredentials'));

            $m->connect('api/account/rate_limit_status.:format',
                        array('action' => 'ApiAccountRateLimitStatus'));

            // favorites

            $m->connect('api/favorites.:format',
                        array('action' => 'ApiTimelineFavorites',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/favorites/:id.:format',
                        array('action' => 'ApiTimelineFavorites',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/favorites/create/:id.:format',
                        array('action' => 'ApiFavoriteCreate',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/favorites/destroy/:id.:format',
                        array('action' => 'ApiFavoriteDestroy',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));
            // blocks

            $m->connect('api/blocks/create/:id.:format',
                        array('action' => 'ApiBlockCreate',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/blocks/destroy/:id.:format',
                        array('action' => 'ApiBlockDestroy',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));
            // help

            $m->connect('api/help/test.:format',
                        array('action' => 'ApiHelpTest',
                              'format' => '(xml|json)'));

            // statusnet

            $m->connect('api/statusnet/version.:format',
                        array('action' => 'ApiStatusnetVersion',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/config.:format',
                        array('action' => 'ApiStatusnetConfig',
                              'format' => '(xml|json)'));

            // For older methods, we provide "laconica" base action

            $m->connect('api/laconica/version.:format',
                        array('action' => 'ApiStatusnetVersion',
                              'format' => '(xml|json)'));

            $m->connect('api/laconica/config.:format',
                        array('action' => 'ApiStatusnetConfig',
                              'format' => '(xml|json)'));

            // Groups and tags are newer than 0.8.1 so no backward-compatibility
            // necessary

            // Groups
            //'list' has to be handled differently, as php will not allow a method to be named 'list'

            $m->connect('api/statusnet/groups/timeline/:id.:format',
                        array('action' => 'ApiTimelineGroup',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/show.:format',
                        array('action' => 'ApiGroupShow',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/show/:id.:format',
                        array('action' => 'ApiGroupShow',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/join.:format',
                        array('action' => 'ApiGroupJoin',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/join/:id.:format',
                        array('action' => 'ApiGroupJoin',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/leave.:format',
                        array('action' => 'ApiGroupLeave',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/leave/:id.:format',
                        array('action' => 'ApiGroupLeave',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/is_member.:format',
                        array('action' => 'ApiGroupIsMember',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/list.:format',
                        array('action' => 'ApiGroupList',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/list/:id.:format',
                        array('action' => 'ApiGroupList',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/list_all.:format',
                        array('action' => 'ApiGroupListAll',
                              'format' => '(xml|json|rss|atom)'));

            $m->connect('api/statusnet/groups/membership.:format',
                        array('action' => 'ApiGroupMembership',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/membership/:id.:format',
                        array('action' => 'ApiGroupMembership',
                              'id' => '[a-zA-Z0-9]+',
                              'format' => '(xml|json)'));

            $m->connect('api/statusnet/groups/create.:format',
                        array('action' => 'ApiGroupCreate',
                              'format' => '(xml|json)'));
            // Tags
            $m->connect('api/statusnet/tags/timeline/:tag.:format',
                        array('action' => 'ApiTimelineTag',
                              'format' => '(xml|json|rss|atom)'));

            // media related
            $m->connect(
                'api/statusnet/media/upload',
                array('action' => 'ApiMediaUpload')
            );

            // search
            $m->connect('api/search.atom', array('action' => 'ApiSearchAtom'));
            $m->connect('api/search.json', array('action' => 'ApiSearchJSON'));
            $m->connect('api/trends.json', array('action' => 'ApiTrends'));

            $m->connect('api/oauth/request_token',
                        array('action' => 'apioauthrequesttoken'));

            $m->connect('api/oauth/access_token',
                        array('action' => 'apioauthaccesstoken'));

            $m->connect('api/oauth/authorize',
                        array('action' => 'apioauthauthorize'));

            // Admin

            $m->connect('admin/site', array('action' => 'siteadminpanel'));
            $m->connect('admin/design', array('action' => 'designadminpanel'));
            $m->connect('admin/user', array('action' => 'useradminpanel'));
	        $m->connect('admin/access', array('action' => 'accessadminpanel'));
            $m->connect('admin/paths', array('action' => 'pathsadminpanel'));
            $m->connect('admin/sessions', array('action' => 'sessionsadminpanel'));
            $m->connect('admin/sitenotice', array('action' => 'sitenoticeadminpanel'));
            $m->connect('admin/snapshot', array('action' => 'snapshotadminpanel'));

            $m->connect('getfile/:filename',
                        array('action' => 'getfile'),
                        array('filename' => '[A-Za-z0-9._-]+'));

            // In the "root"

            if (common_config('singleuser', 'enabled')) {

                $user = User::siteOwner();

                if (!empty($user)) {
                    $nickname = $user->nickname;
                } else {
                    $nickname = common_config('singleuser', 'nickname');
                    if (empty($nickname)) {
                        throw new ServerException(_("No single user defined for single-user mode."));
                    }
                }

                foreach (array('subscriptions', 'subscribers',
                               'all', 'foaf', 'xrds',
                               'replies', 'microsummary', 'hcard') as $a) {
                    $m->connect($a,
                                array('action' => $a,
                                      'nickname' => $nickname));
                }

                foreach (array('subscriptions', 'subscribers') as $a) {
                    $m->connect($a.'/:tag',
                                array('action' => $a,
                                      'nickname' => $nickname),
                                array('tag' => '[a-zA-Z0-9]+'));
                }

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

                $m->connect('avatar/:size',
                            array('action' => 'avatarbynickname',
                                  'nickname' => $nickname),
                            array('size' => '(original|96|48|24)'));

                $m->connect('tag/:tag/rss',
                            array('action' => 'userrss',
                                  'nickname' => $nickname),
                            array('tag' => '[\pL\pN_\-\.]{1,64}'));

                $m->connect('tag/:tag',
                            array('action' => 'showstream',
                                  'nickname' => $nickname),
                            array('tag' => '[\pL\pN_\-\.]{1,64}'));

                $m->connect('rsd.xml',
                            array('action' => 'rsd',
                                  'nickname' => $nickname));

                $m->connect('',
                            array('action' => 'showstream',
                                  'nickname' => $nickname));

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
                               'nudge', 'all', 'foaf', 'xrds',
                               'replies', 'inbox', 'outbox', 'microsummary', 'hcard') as $a) {
                    $m->connect(':nickname/'.$a,
                                array('action' => $a),
                                array('nickname' => '[a-zA-Z0-9]{1,64}'));
                }

                foreach (array('subscriptions', 'subscribers') as $a) {
                    $m->connect(':nickname/'.$a.'/:tag',
                                array('action' => $a),
                                array('tag' => '[a-zA-Z0-9]+',
                                      'nickname' => '[a-zA-Z0-9]{1,64}'));
                }

                foreach (array('rss', 'groups') as $a) {
                    $m->connect(':nickname/'.$a,
                                array('action' => 'user'.$a),
                                array('nickname' => '[a-zA-Z0-9]{1,64}'));
                }

                foreach (array('all', 'replies', 'favorites') as $a) {
                    $m->connect(':nickname/'.$a.'/rss',
                                array('action' => $a.'rss'),
                                array('nickname' => '[a-zA-Z0-9]{1,64}'));
                }

                $m->connect(':nickname/favorites',
                            array('action' => 'showfavorites'),
                            array('nickname' => '[a-zA-Z0-9]{1,64}'));

                $m->connect(':nickname/avatar/:size',
                            array('action' => 'avatarbynickname'),
                            array('size' => '(original|96|48|24)',
                                  'nickname' => '[a-zA-Z0-9]{1,64}'));

                $m->connect(':nickname/tag/:tag/rss',
                            array('action' => 'userrss'),
                            array('nickname' => '[a-zA-Z0-9]{1,64}'),
                            array('tag' => '[\pL\pN_\-\.]{1,64}'));

                $m->connect(':nickname/tag/:tag',
                            array('action' => 'showstream'),
                            array('nickname' => '[a-zA-Z0-9]{1,64}'),
                            array('tag' => '[\pL\pN_\-\.]{1,64}'));

                $m->connect(':nickname/rsd.xml',
                            array('action' => 'rsd'),
                            array('nickname' => '[a-zA-Z0-9]{1,64}'));

                $m->connect(':nickname',
                            array('action' => 'showstream'),
                            array('nickname' => '[a-zA-Z0-9]{1,64}'));
            }

            // user stuff

            Event::handle('RouterInitialized', array($m));
        }

        return $m;
    }

    function map($path)
    {
        try {
            $match = $this->m->match($path);
        } catch (Net_URL_Mapper_InvalidException $e) {
            common_log(LOG_ERR, "Problem getting route for $path - " .
                       $e->getMessage());
            $cac = new ClientErrorAction("Page not found.", 404);
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
        }
        return $url;
    }
}

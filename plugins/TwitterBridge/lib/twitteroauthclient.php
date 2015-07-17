<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for doing OAuth calls against Twitter
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
 * @category  Integration
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for talking to the Twitter API with OAuth.
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class TwitterOAuthClient extends OAuthClient
{
    public static $requestTokenURL = 'https://api.twitter.com/oauth/request_token';
    public static $authorizeURL    = 'https://api.twitter.com/oauth/authorize';
    public static $signinUrl       = 'https://api.twitter.com/oauth/authenticate';
    public static $accessTokenURL  = 'https://api.twitter.com/oauth/access_token';

    /**
     * Constructor
     *
     * @param string $oauth_token        the user's token
     * @param string $oauth_token_secret the user's token secret
     *
     * @return nothing
     */
    function __construct($oauth_token = null, $oauth_token_secret = null)
    {
        $consumer_key    = common_config('twitter', 'consumer_key');
        $consumer_secret = common_config('twitter', 'consumer_secret');

        if (empty($consumer_key) && empty($consumer_secret)) {
            $consumer_key = common_config(
                'twitter',
                'global_consumer_key'
            );
            $consumer_secret = common_config(
                'twitter',
                'global_consumer_secret'
            );
        }

        parent::__construct(
            $consumer_key,
            $consumer_secret,
            $oauth_token,
            $oauth_token_secret
        );
    }

    // XXX: the following two functions are to support the horrible hack
    // of using the credentils field in Foreign_link to store both
    // the access token and token secret.  This hack should go away with
    // 0.9, in which we can make DB changes and add a new column for the
    // token itself.

    static function packToken($token)
    {
        return implode(chr(0), array($token->key, $token->secret));
    }

    static function unpackToken($str)
    {
        $vals = explode(chr(0), $str);
        return new OAuthToken($vals[0], $vals[1]);
    }

    static function isPackedToken($str)
    {
        if (strpos($str, chr(0)) === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Gets a request token from Twitter
     *
     * @return OAuthToken $token the request token
     */
    function getTwitterRequestToken()
    {
        return parent::getRequestToken(
            self::$requestTokenURL,
            common_local_url('twitterauthorization')
        );
    }

    /**
     * Builds a link to Twitter's endpoint for authorizing a request token
     *
     * @param OAuthToken $request_token token to authorize
     *
     * @return the link
     */
    function getAuthorizeLink($request_token, $signin = false)
    {
        $url = ($signin) ? self::$signinUrl : self::$authorizeURL;

        return parent::getAuthorizeLink($url,
                                        $request_token,
                                        common_local_url('twitterauthorization'));
    }

    /**
     * Fetches an access token from Twitter
     *
     * @param string $verifier 1.0a verifier
     *
     * @return OAuthToken $token the access token
     */
    function getTwitterAccessToken($verifier = null)
    {
        return parent::getAccessToken(
            self::$accessTokenURL,
            $verifier
        );
    }

    /**
     * Calls Twitter's /account/verify_credentials API method
     *
     * @return mixed the Twitter user
     */
    function verifyCredentials()
    {
        $url          = 'https://api.twitter.com/1.1/account/verify_credentials.json';
        $response     = $this->oAuthGet($url);
        $twitter_user = json_decode($response);
        return $twitter_user;
    }

    /**
     * Calls Twitter's /statuses/update API method
     *
     * @param string $status  text of the status
     * @param mixed  $params  optional other parameters to pass to Twitter,
     *                        as defined. For back-compatibility, if an int
     *                        is passed we'll consider it a reply-to ID.
     *
     * @return mixed the status
     */
    function statusesUpdate($status, $params=array())
    {
        $url      = 'https://api.twitter.com/1.1/statuses/update.json';
        if (is_numeric($params)) {
            $params = array('in_reply_to_status_id' => intval($params));
        }
        $params['status'] = $status;
        // We don't have to pass 'source' as the oauth key is tied to an app.

        $response = $this->oAuthPost($url, $params);
        $status   = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /statuses/home_timeline API method
     *
     * @param int    $since_id    show statuses after this id
     * @param string $timelineUri timeline to poll statuses from
     * @param int    $max_id      show statuses before this id
     * @param int    $cnt         number of statuses to show
     * @param int    $page        page number
     *
     * @return mixed an array of statuses
     */
    function statusesTimeline($since_id = null, $timelineUri = 'home_timeline',
                              $max_id = null, $cnt = 200, $page = null)
    {
        $url    = 'https://api.twitter.com/1.1/statuses/'.$timelineUri.'.json';

        $params = array('include_entities' => 'true',
                        'include_rts'      => 'true');

        if (!empty($since_id)) {
            $params['since_id'] = $since_id;
        }
        if (!empty($max_id)) {
            $params['max_id'] = $max_id;
        }
        if (!empty($cnt)) {
            $params['count'] = $cnt;
        }
        if (!empty($page)) {
            $params['page'] = $page;
        }

        $response = $this->oAuthGet($url, $params);
        $statuses = json_decode($response);
        return $statuses;
    }

    /**
     * Calls Twitter's /statuses/friends API method
     *
     * @param int $id          id of the user whom you wish to see friends of
     * @param int $user_id     numerical user id
     * @param int $screen_name screen name
     * @param int $page        page number
     *
     * @return mixed an array of twitter users and their latest status
     */
    function statusesFriends($id = null, $user_id = null, $screen_name = null,
                             $page = null)
    {
        $url = "https://api.twitter.com/1.1/friends/list.json";

        $params = array();

        if (!empty($id)) {
            $params['id'] = $id;
        }

        if (!empty($user_id)) {
            $params['user_id'] = $user_id;
        }

        if (!empty($screen_name)) {
            $params['screen_name'] = $screen_name;
        }

        if (!empty($page)) {
            $params['page'] = $page;
        }

        $response = $this->oAuthGet($url, $params);
        $friends  = json_decode($response);
        return $friends;
    }

    /**
     * Calls Twitter's /statuses/friends/ids API method
     *
     * @param int $id          id of the user whom you wish to see friends of
     * @param int $user_id     numerical user id
     * @param int $screen_name screen name
     * @param int $page        page number
     *
     * @return mixed a list of ids, 100 per page
     */
    function friendsIds($id = null, $user_id = null, $screen_name = null,
                         $page = null)
    {
        $url = "https://api.twitter.com/1.1/friends/ids.json";

        $params = array();

        if (!empty($id)) {
            $params['id'] = $id;
        }

        if (!empty($user_id)) {
            $params['user_id'] = $user_id;
        }

        if (!empty($screen_name)) {
            $params['screen_name'] = $screen_name;
        }

        if (!empty($page)) {
            $params['page'] = $page;
        }

        $response = $this->oAuthGet($url, $params);
        $ids      = json_decode($response);
        return $ids;
    }

    /**
     * Calls Twitter's /statuses/retweet/id.json API method
     *
     * @param int $id id of the notice to retweet
     *
     * @return retweeted status
     */

    function statusesRetweet($id)
    {
        $url = "https://api.twitter.com/1.1/statuses/retweet/$id.json";
        $response = $this->oAuthPost($url);
        $status = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /favorites/create API method
     *
     * @param int $id ID of the status to favorite
     *
     * @return object faved status
     */

    function favoritesCreate($id)
    {
        $url = "https://api.twitter.com/1.1/favorites/create.json";
        $params=array();
        $params['id'] = $id;
        $response = $this->oAuthPost($url, $params);
        $status = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /favorites/destroy API method
     *
     * @param int $id ID of the status to unfavorite
     *
     * @return object unfaved status
     */

    function favoritesDestroy($id)
    {
        $url = "https://api.twitter.com/1.1/favorites/destroy.json";
        $params=array();
        $params['id'] = $id;
        $response = $this->oAuthPost($url,$params);
        $status = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /statuses/destroy API method
     *
     * @param int $id ID of the status to destroy
     *
     * @return object destroyed
     */

    function statusesDestroy($id)
    {
        $url = "https://api.twitter.com/1.1/statuses/destroy/$id.json";
        $response = $this->oAuthPost($url);
        $status = json_decode($response);
        return $status;
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for doing HTTP basic auth calls against Twitter
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * General Exception wrapper for HTTP basic auth errors
 *
 *  @category Integration
 *  @package  StatusNet
 *  @author   Zach Copley <zach@status.net>
 *  @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 *  @link     http://status.net/
 *
 */
class BasicAuthException extends Exception
{
}

/**
 * Class for talking to the Twitter API with HTTP Basic Auth.
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class TwitterBasicAuthClient
{
    var $screen_name = null;
    var $password    = null;

    /**
     * constructor
     *
     * @param Foreign_link $flink a Foreign_link storing the
     *                            Twitter user's password, etc.
     */
    function __construct($flink)
    {
        $fuser             = $flink->getForeignUser();
        $this->screen_name = $fuser->nickname;
        $this->password    = $flink->credentials;
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
    function statusesUpdate($status, $in_reply_to_status_id = null)
    {
        $url      = 'https://twitter.com/statuses/update.json';
        if (is_numeric($params)) {
            $params = array('in_reply_to_status_id' => intval($params));
        }
        $params['status'] = $status;
        $params['source'] = common_config('integration', 'source');
        $response = $this->httpRequest($url, $params);
        $status   = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /statuses/friends_timeline API method
     *
     * @param int $since_id show statuses after this id
     * @param int $max_id   show statuses before this id
     * @param int $cnt      number of statuses to show
     * @param int $page     page number
     *
     * @return mixed an array of statuses
     */
    function statusesFriendsTimeline($since_id = null, $max_id = null,
                                     $cnt = null, $page = null)
    {
        $url    = 'https://twitter.com/statuses/friends_timeline.json';
        $params = array('since_id' => $since_id,
                        'max_id' => $max_id,
                        'count' => $cnt,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
        $statuses = json_decode($response);
        return $statuses;
    }

    /**
     * Calls Twitter's /statuses/home_timeline API method
     *
     * @param int $since_id show statuses after this id
     * @param int $max_id   show statuses before this id
     * @param int $cnt      number of statuses to show
     * @param int $page     page number
     *
     * @return mixed an array of statuses similar to friends timeline but including retweets
     */
    function statusesHomeTimeline($since_id = null, $max_id = null,
                                     $cnt = null, $page = null)
    {
        $url    = 'https://twitter.com/statuses/home_timeline.json';
        $params = array('since_id' => $since_id,
                        'max_id' => $max_id,
                        'count' => $cnt,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
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
        $url = "https://twitter.com/statuses/friends.json";

        $params = array('id' => $id,
                        'user_id' => $user_id,
                        'screen_name' => $screen_name,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
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
        $url = "https://twitter.com/friends/ids.json";

        $params = array('id' => $id,
                        'user_id' => $user_id,
                        'screen_name' => $screen_name,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
        $ids      = json_decode($response);
        return $ids;
    }

    /**
     * Make an HTTP request
     *
     * @param string $url    Where to make the request
     * @param array  $params post parameters
     *
     * @return mixed the request
     * @throws BasicAuthException
     */
    function httpRequest($url, $params = null, $auth = true)
    {
        $request = HTTPClient::start();
        $request->setConfig(array(
            'follow_redirects' => true,
            'connect_timeout' => 120,
            'timeout' => 120,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false
        ));

        if ($auth) {
            $request->setAuth($this->screen_name, $this->password);
        }

        if (isset($params)) {
            // Twitter is strict about accepting invalid "Expect" headers
            $headers = array('Expect:');
            $response = $request->post($url, $headers, $params);
        } else {
            $response = $request->get($url);
        }

        $code = $response->getStatus();

        if ($code < 200 || $code >= 400) {
            throw new BasicAuthException($response->getBody(), $code);
        }

        return $response->getBody();
    }

}

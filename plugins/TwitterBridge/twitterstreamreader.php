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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/**
 * Base class for reading Twitter's User Streams and Site Streams
 * real-time streaming APIs.
 *
 * Caller can hook event callbacks for various types of messages;
 * the data from the stream and some context info will be passed
 * on to the callbacks.
 */
abstract class TwitterStreamReader extends JsonStreamReader
{
    protected $callbacks = array();

    function __construct(TwitterOAuthClient $auth, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->oauth = $auth;
    }

    public function connect($method, $params=array())
    {
        $url = $this->oAuthUrl($this->baseUrl . '/' . $method, $params);
        return parent::connect($url);
    }

    /**
     * Sign our target URL with OAuth auth stuff.
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    protected function oAuthUrl($url, $params=array())
    {
        // In an ideal world this would be better encapsulated. :)
        $request = OAuthRequest::from_consumer_and_token($this->oauth->consumer,
            $this->oauth->token, 'GET', $url, $params);
        $request->sign_request($this->oauth->sha1_method,
            $this->oauth->consumer, $this->oauth->token);

        return $request->to_url();
    }

    /**
     * Add an event callback to receive notifications when things come in
     * over the wire.
     *
     * Callbacks should be in the form: function(object $data, array $context)
     * where $context may list additional data on some streams, such as the
     * user to whom the message should be routed.
     *
     * Available events:
     *
     * Messaging:
     *
     * 'status': $data contains a status update in standard Twitter JSON format.
     *      $data->user: sending user in standard Twitter JSON format.
     *      $data->text... etc
     *
     * 'direct_message': $data contains a direct message in standard Twitter JSON format.
     *      $data->sender: sending user in standard Twitter JSON format.
     *      $data->recipient: receiving user in standard Twitter JSON format.
     *      $data->text... etc
     *
     *
     * Out of band events:
     *
     * 'follow': User has either started following someone, or is being followed.
     *      $data->source: following user in standard Twitter JSON format.
     *      $data->target: followed user in standard Twitter JSON format.
     *
     * 'favorite': Someone has favorited a status update.
     *      $data->source: user doing the favoriting, in standard Twitter JSON format.
     *      $data->target: user whose status was favorited, in standard Twitter JSON format.
     *      $data->target_object: the favorited status update in standard Twitter JSON format.
     *
     * 'unfavorite': Someone has unfavorited a status update.
     *      $data->source: user doing the unfavoriting, in standard Twitter JSON format.
     *      $data->target: user whose status was unfavorited, in standard Twitter JSON format.
     *      $data->target_object: the unfavorited status update in standard Twitter JSON format.
     *
     *
     * Meta information:
     *
     * 'friends':
     *      $data->friends: array of user IDs of the current user's friends.
     *
     * 'delete': Advisory that a Twitter status has been deleted; nice clients
     *           should follow suit.
     *      $data->id: ID of status being deleted
     *      $data->user_id: ID of its owning user
     *
     * 'scrub_geo': Advisory that a user is clearing geo data from their status
     *              stream; nice clients should follow suit.
     *      $data->user_id: ID of user
     *      $data->up_to_status_id: any notice older than this should be scrubbed.
     *
     * 'limit': Advisory that tracking has hit a resource limit.
     *      $data->track
     *
     * 'raw': receives the full JSON data for all message types.
     *
     * @param string $event
     * @param callable $callback
     */
    public function hookEvent($event, $callback)
    {
        $this->callbacks[$event][] = $callback;
    }

    /**
     * Call event handler callbacks for the given event.
     *
     * @param string $event
     * @param mixed $arg1 ... one or more params to pass on
     */
    protected function fireEvent($event, $arg1)
    {
        if (array_key_exists($event, $this->callbacks)) {
            $args = array_slice(func_get_args(), 1);
            foreach ($this->callbacks[$event] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    protected function handleJson(stdClass $data)
    {
        $this->routeMessage($data);
    }

    abstract protected function routeMessage(stdClass $data);

    /**
     * Send the decoded JSON object out to any event listeners.
     *
     * @param array $data
     * @param array $context optional additional context data to pass on
     */
    protected function handleMessage(stdClass $data, array $context=array())
    {
        $this->fireEvent('raw', $data, $context);

        if (isset($data->text)) {
            $this->fireEvent('status', $data, $context);
            return;
        }
        if (isset($data->event)) {
            $this->fireEvent($data->event, $data, $context);
            return;
        }
        if (isset($data->friends)) {
            $this->fireEvent('friends', $data, $context);
        }

        $knownMeta = array('delete', 'scrub_geo', 'limit', 'direct_message');
        foreach ($knownMeta as $key) {
            if (isset($data->$key)) {
                $this->fireEvent($key, $data->$key, $context);
                return;
            }
        }
    }
}

/**
 * Multiuser stream listener for Twitter Site Streams API
 * http://dev.twitter.com/pages/site_streams
 *
 * The site streams API allows listening to updates for multiple users.
 * Pass in the user IDs to listen to in via followUser() -- note they
 * must each have a valid OAuth token for the application ID we're
 * connecting as.
 *
 * You'll need to be connecting with the auth keys for the user who
 * owns the application registration.
 *
 * The user each message is destined for will be passed to event handlers
 * in $context['for_user_id'].
 */
class TwitterSiteStream extends TwitterStreamReader
{
    protected $userIds;

    public function __construct(TwitterOAuthClient $auth, $baseUrl='https://sitestream.twitter.com')
    {
        parent::__construct($auth, $baseUrl);
    }

    public function connect($method='2b/site.json')
    {
        $params = array();
        if ($this->userIds) {
            $params['follow'] = implode(',', $this->userIds);
        }
        return parent::connect($method, $params);
    }

    /**
     * Set the users whose home streams should be pulled.
     * They all must have valid oauth tokens for this application.
     *
     * Must be called before connect().
     *
     * @param array $userIds
     */
    function followUsers($userIds)
    {
        $this->userIds = $userIds;
    }

    /**
     * Each message in the site stream tells us which user ID it should be
     * routed to; we'll need that to let the caller know what to do.
     *
     * @param array $data
     */
    function routeMessage(stdClass $data)
    {
        $context = array(
            'source' => 'sitestream',
            'for_user' => $data->for_user
        );
        parent::handleMessage($data->message, $context);
    }
}

/**
 * Stream listener for Twitter User Streams API
 * http://dev.twitter.com/pages/user_streams
 *
 * This will pull the home stream and additional events just for the user
 * we've authenticated as.
 */
class TwitterUserStream extends TwitterStreamReader
{
    public function __construct(TwitterOAuthClient $auth, $baseUrl='https://userstream.twitter.com')
    {
        parent::__construct($auth, $baseUrl);
    }

    public function connect($method='2/user.json')
    {
        return parent::connect($method);
    }

    /**
     * Each message in the user stream is just ready to go.
     *
     * @param array $data
     */
    function routeMessage(stdClass $data)
    {
        $context = array(
            'source' => 'userstream'
        );
        parent::handleMessage($data, $context);
    }
}

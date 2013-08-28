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

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

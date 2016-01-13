<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class to ping an rssCloud endpoint when a feed has been updated
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class for notifying cloud-enabled RSS aggregators that StatusNet
 * feeds have been updated.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RSSCloudNotifier
{
    const MAX_FAILURES = 3;

    /**
     * Send an HTTP GET to the notification handler with a
     * challenge string to see if it repsonds correctly.
     *
     * @param string $endpoint URL of the notification handler
     * @param string $feed     the feed being subscribed to
     *
     * @return boolean success
     */
    function challenge($endpoint, $feed)
    {
        $code   = common_confirmation_code(128);
        $params = array('url' => $feed, 'challenge' => $code);
        $url    = $endpoint . '?' . http_build_query($params);

        try {
            $client   = new HTTPClient();
            $response = $client->get($url);
        } catch (Exception $e) {
            common_log(LOG_INFO,
                       'RSSCloud plugin - failure testing notify handler ' .
                       $endpoint . ' - '  . $e->getMessage());
            return false;
        }

        // Check response is betweet 200 and 299 and body contains challenge data

        $status = $response->getStatus();
        $body   = $response->getBody();

        if ($status >= 200 && $status < 300) {
            // NOTE: the spec says that the body must contain the string
            // challenge.  It doesn't say that the body must contain the
            // challenge string ONLY, although that seems to be the way
            // the other implementors have interpreted it.

            if (strpos($body, $code) !== false) {
                common_log(LOG_INFO, 'RSSCloud plugin - ' .
                           "success testing notify handler:  $endpoint");
                return true;
            } else {
                common_log(LOG_INFO, 'RSSCloud plugin - ' .
                          'challenge/repsonse failed for notify handler ' .
                           $endpoint);
                common_debug('body = ' . var_export($body, true));
                return false;
            }
        } else {
            common_log(LOG_INFO, 'RSSCloud plugin - ' .
                       "failure testing notify handler:  $endpoint " .
                       ' - got HTTP ' . $status);
            common_debug('body = ' . var_export($body, true));
            return false;
        }
    }

    /**
     * HTTP POST a notification that a feed has been updated
     * ('ping the cloud').
     *
     * @param String $endpoint URL of the notification handler
     * @param String $feed     the feed being subscribed to
     *
     * @return boolean success
     */
    function postUpdate($endpoint, $feed)
    {
        $headers  = array();
        $postdata = array('url' => $feed);

        try {
            $client   = new HTTPClient();
            $response = $client->post($endpoint, $headers, $postdata);
        } catch (Exception $e) {
            common_log(LOG_INFO, 'RSSCloud plugin - failure notifying ' .
                       $endpoint . ' that feed ' . $feed .
                       ' has changed: ' . $e->getMessage());
            return false;
        }

        $status = $response->getStatus();

        if ($status >= 200 && $status < 300) {
            common_log(LOG_INFO, 'RSSCloud plugin - success notifying ' .
                       $endpoint . ' that feed ' . $feed . ' has changed.');
            return true;
        } else {
            common_log(LOG_INFO, 'RSSCloud plugin - failure notifying ' .
                       $endpoint . ' that feed ' . $feed .
                       ' has changed: got HTTP ' . $status);
            return false;
        }
    }

    /**
     * Notify all subscribers to a profile feed that it has changed.
     *
     * @param Profile $profile the profile whose feed has been
     *        updated
     *
     * @return boolean success
     */
    function notify($profile)
    {
        $feed = common_path('api/statuses/user_timeline/') .
          $profile->id . '.rss';

        $cloudSub = new RSSCloudSubscription();

        $cloudSub->subscribed = $profile->id;

        if ($cloudSub->find()) {
            while ($cloudSub->fetch()) {
                $result = $this->postUpdate($cloudSub->url, $feed);
                if ($result == false) {
                    $this->handleFailure($cloudSub);
                }
            }
        }

        return true;
    }

    /**
     * Handle problems posting cloud notifications. Increment the failure
     * count, or delete the subscription if the maximum number of failures
     * is exceeded.
     *
     * XXX: Redo with proper DB_DataObject methods once I figure out what
     * what the problem is with pluginized DB_DataObjects. -Z
     *
     * @param RSSCloudSubscription $cloudSub the subscription in question
     *
     * @return boolean success
     */
    function handleFailure($cloudSub)
    {
        $failCnt = $cloudSub->failures + 1;

        if ($failCnt == self::MAX_FAILURES) {

            common_log(LOG_INFO,
                       'Deleting RSSCloud subcription ' .
                       '(max failure count reached), profile: ' .
                       $cloudSub->subscribed .
                       ' handler: ' .
                       $cloudSub->url);

            // XXX: WTF! ->delete() doesn't work. Clearly, there are some issues with
            // the DB_DataObject, or my understanding of it.  Have to drop into SQL.

            // $result = $cloudSub->delete();

            $qry = 'DELETE from rsscloud_subscription' .
              ' WHERE subscribed = ' . $cloudSub->subscribed .
              ' AND url = \'' . $cloudSub->url . '\'';

            $result = $cloudSub->query($qry);

            if (!$result) {
                common_log_db_error($cloudSub, 'DELETE', __FILE__);
                common_log(LOG_ERR, 'Could not delete RSSCloud subscription.');
            }
        } else {
            common_debug('Updating failure count on RSSCloud subscription. ' .
                         $failCnt);

            $failCnt = $cloudSub->failures + 1;

            // XXX: ->update() not working either, gar!

            $qry = 'UPDATE rsscloud_subscription' .
              ' SET failures = ' . $failCnt .
              ' WHERE subscribed = ' . $cloudSub->subscribed .
              ' AND url = \'' . $cloudSub->url . '\'';

            $result = $cloudSub->query($qry);

            if (!$result) {
                common_log_db_error($cloudsub, 'UPDATE', __FILE__);
                common_log(LOG_ERR,
                           'Could not update failure ' .
                           'count on RSSCloud subscription');
            }
        }
    }
}

<?php
/**
 * Action to let RSSCloud aggregators request update notification when
 * user profile feeds change.
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Action class to handle RSSCloud notification (subscription) requests
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RSSCloudRequestNotifyAction extends Action
{
    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->ip   = $_SERVER['REMOTE_ADDR'];
        $this->port = $this->arg('port');
        $this->path = $this->arg('path');

        if ($this->path[0] != '/') {
            $this->path = '/' . $this->path;
        }

        $this->protocol  = $this->arg('protocol');
        $this->procedure = $this->arg('notifyProcedure');
        $this->domain    = $this->arg('domain');

        $this->feeds = $this->getFeeds();

        return true;
    }

    /**
     * Handle the request
     *
     * Checks for all the required parameters for a subscription,
     * validates that the feed being subscribed to is real, and then
     * saves the subsctiption.
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            // TRANS: Form validation error displayed when POST is not used.
            $this->showResult(false, _m('Request must be POST.'));
            return;
        }

        $missing = array();

        if (empty($this->port)) {
            $missing[] = 'port';
        }

        if (empty($this->path)) {
            $missing[] = 'path';
        }

        if (empty($this->protocol)) {
            $missing[] = 'protocol';
        } else if (strtolower($this->protocol) != 'http-post') {
            // TRANS: Form validation error displayed when HTTP POST is not used.
            $msg = _m('Only HTTP POST notifications are supported at this time.');
            $this->showResult(false, $msg);
            return;
        }

        if (!isset($this->procedure)) {
            $missing[] = 'notifyProcedure';
        }

        if (!empty($missing)) {
            // TRANS: List separator.
            $separator = _m('SEPARATOR',', ');
            // TRANS: Form validation error displayed when a request body is missing expected parameters.
            // TRANS: %s is a list of parameters separated by a list separator (default: ", ").
            $msg = sprintf(_m('The following parameters were missing from the request body: %s.'),implode($separator, $missing));
            $this->showResult(false, $msg);
            return;
        }

        if (empty($this->feeds)) {
            // TRANS: Form validation error displayed when not providing any valid profile feed URLs.
            $msg = _m('You must provide at least one valid profile feed URL ' .
              '(url1, url2, url3 ... urlN).');
            $this->showResult(false, $msg);
            return;
        }

        // We have to validate everything before saving anything.
        // We only return one success or failure no matter how
        // many feeds the subscriber is trying to subscribe to
        foreach ($this->feeds as $feed) {
            if (!$this->validateFeed($feed)) {
                $nh = $this->getNotifyUrl();
                common_log(LOG_WARNING,
                           "RSSCloud plugin - $nh tried to subscribe to invalid feed: $feed");

                // TRANS: Form validation error displayed when not providing a valid feed URL.
                $msg = _m('Feed subscription failed: Not a valid feed.');
                $this->showResult(false, $msg);
                return;
            }

            if (!$this->testNotificationHandler($feed)) {
                // TRANS: Form validation error displayed when feed subscription failed.
                $msg = _m('Feed subscription failed: ' .
                'Notification handler does not respond correctly.');
                $this->showResult(false, $msg);
                return;
            }
        }

        foreach ($this->feeds as $feed) {
            $this->saveSubscription($feed);
        }

        // XXX: What to do about deleting stale subscriptions?
        // 25 hours seems harsh. WordPress doesn't ever remove
        // subscriptions.
        // TRANS: Success message after subscribing to one or more feeds.
        $msg = _m('Thanks for the subscription. ' .
          'When the feed(s) update(s), you will be notified.');

        $this->showResult(true, $msg);
    }

    /**
     * Validate that the requested feed is one we serve
     * up via RSSCloud.
     *
     * @param string $feed the feed in question
     *
     * @return void
     */
    function validateFeed($feed)
    {
        $user = $this->userFromFeed($feed);

        if (empty($user)) {
            return false;
        }

        return true;
    }

    /**
     * Pull all of the urls (url1, url2, url3...urlN) that
     * the subscriber wants to subscribe to.
     *
     * @return array $feeds the list of feeds
     */
    function getFeeds()
    {
        $feeds = array();

        while (list($key, $feed) = each($this->args)) {
            if (preg_match('/^url\d*$/', $key)) {
                $feeds[] = $feed;
            }
        }

        return $feeds;
    }

    /**
     * Test that a notification handler is there and is reponding
     * correctly.  This is called before adding a subscription.
     *
     * @param string $feed the feed to verify
     *
     * @return boolean success result
     */
    function testNotificationHandler($feed)
    {
        $notifyUrl = $this->getNotifyUrl();

        $notifier = new RSSCloudNotifier();

        if (isset($this->domain)) {
            // 'domain' param set, so we have to use GET and send a challenge
            common_log(LOG_INFO,
                       'RSSCloud plugin - Testing notification handler with challenge: ' .
                       $notifyUrl);
            return $notifier->challenge($notifyUrl, $feed);
        } else {
            common_log(LOG_INFO, 'RSSCloud plugin - Testing notification handler: ' .
                       $notifyUrl);

            return $notifier->postUpdate($notifyUrl, $feed);
        }
    }

    /**
     * Build the URL for the notification handler based on the
     * parameters passed in with the subscription request.
     *
     * @return string notification handler url
     */
    function getNotifyUrl()
    {
        if (isset($this->domain)) {
            return 'http://' . $this->domain . ':' . $this->port . $this->path;
        } else {
            return 'http://' . $this->ip . ':' . $this->port . $this->path;
        }
    }

    /**
     * Uses the nickname part of the subscribed feed URL to figure out
     * whethere there's really a user with such a feed.  Used to
     * validate feeds before adding a subscription.
     *
     * @param string $feed the feed in question
     *
     * @return boolean success
     */
    function userFromFeed($feed)
    {
        // We only do canonical RSS2 profile feeds (specified by ID), e.g.:
        // http://www.example.com/api/statuses/user_timeline/2.rss
        $path  = common_path('api/statuses/user_timeline/');
        $valid = '%^' . $path . '(?<id>.*)\.rss$%';

        if (preg_match($valid, $feed, $matches)) {
            $user = User::staticGet('id', $matches['id']);
            if (!empty($user)) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Save an RSSCloud subscription
     *
     * @param string $feed a valid profile feed
     *
     * @return boolean success result
     */
    function saveSubscription($feed)
    {
        $user = $this->userFromFeed($feed);

        $notifyUrl = $this->getNotifyUrl();

        $sub = RSSCloudSubscription::getSubscription($user->id, $notifyUrl);

        if ($sub) {
            common_log(LOG_INFO, "RSSCloud plugin - $notifyUrl refreshed subscription" .
                         " to user $user->nickname (id: $user->id).");
        } else {
            $sub = new RSSCloudSubscription();

            $sub->subscribed = $user->id;
            $sub->url        = $notifyUrl;
            $sub->created    = common_sql_now();

            if (!$sub->insert()) {
                common_log_db_error($sub, 'INSERT', __FILE__);
                return false;
            }

            common_log(LOG_INFO, "RSSCloud plugin - $notifyUrl subscribed" .
                       " to user $user->nickname (id: $user->id)");
        }

        return true;
    }

    /**
     * Show an XML message indicating the subscription
     * was successful or failed.
     *
     * @param boolean $success whether it was good or bad
     * @param string  $msg     the message to output
     *
     * @return boolean success result
     */
    function showResult($success, $msg)
    {
        $this->startXML();
        $this->elementStart('notifyResult',
                            array('success' => ($success) ? 'true' : 'false',
                                  'msg'     => $msg));
        $this->endXML();
    }
}

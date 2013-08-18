<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * Integrated PuSH hub; lets us only ping them what need it.
 * @package Hub
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Things to consider...
 * should we purge incomplete subscriptions that never get a verification pingback?
 * when can we send subscription renewal checks?
 *    - at next send time probably ok
 * when can we handle trimming of subscriptions?
 *    - at next send time probably ok
 * should we keep a fail count?
 */
class PushHubAction extends Action
{
    function arg($arg, $def=null)
    {
        // PHP converts '.'s in incoming var names to '_'s.
        // It also merges multiple values, which'll break hub.verify and hub.topic for publishing
        // @fixme handle multiple args
        $arg = str_replace('hub.', 'hub_', $arg);
        return parent::arg($arg, $def);
    }

    function prepare($args)
    {
        StatusNet::setApi(true); // reduce exception reports to aid in debugging
        return parent::prepare($args);
    }

    function handle()
    {
        $mode = $this->trimmed('hub.mode');
        switch ($mode) {
        case "subscribe":
        case "unsubscribe":
            $this->subunsub($mode);
            break;
        case "publish":
            // TRANS: Client exception.
            throw new ClientException(_m('Publishing outside feeds not supported.'), 400);
        default:
            // TRANS: Client exception. %s is a mode.
            throw new ClientException(sprintf(_m('Unrecognized mode "%s".'),$mode), 400);
        }
    }

    /**
     * Process a request for a new or modified PuSH feed subscription.
     * If asynchronous verification is requested, updates won't be saved immediately.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   400 Bad Request - rejecting this (not specifically spec'd)
     */
    function subunsub($mode)
    {
        $callback = $this->argUrl('hub.callback');

        $topic = $this->argUrl('hub.topic');
        if (!$this->recognizedFeed($topic)) {
            // TRANS: Client exception. %s is a topic.
            throw new ClientException(sprintf(_m('Unsupported hub.topic %s this hub only serves local user and group Atom feeds.'),$topic));
        }

        $verify = $this->arg('hub.verify'); // @fixme may be multiple
        if ($verify != 'sync' && $verify != 'async') {
            // TRANS: Client exception. %s is sync or async.
            throw new ClientException(sprintf(_m('Invalid hub.verify "%s". It must be sync or async.'),$verify));
        }

        $lease = $this->arg('hub.lease_seconds', null);
        if ($mode == 'subscribe' && $lease != '' && !preg_match('/^\d+$/', $lease)) {
            // TRANS: Client exception. %s is the invalid lease value.
            throw new ClientException(sprintf(_m('Invalid hub.lease "%s". It must be empty or positive integer.'),$lease));
        }

        $token = $this->arg('hub.verify_token', null);

        $secret = $this->arg('hub.secret', null);
        if ($secret != '' && strlen($secret) >= 200) {
            // TRANS: Client exception. %s is the invalid hub secret.
            throw new ClientException(sprintf(_m('Invalid hub.secret "%s". It must be under 200 bytes.'),$secret));
        }

        $sub = HubSub::getKV($topic, $callback);
        if (!$sub) {
            // Creating a new one!
            $sub = new HubSub();
            $sub->topic = $topic;
            $sub->callback = $callback;
        }
        if ($mode == 'subscribe') {
            if ($secret) {
                $sub->secret = $secret;
            }
            if ($lease) {
                $sub->setLease(intval($lease));
            }
        }

        if (!common_config('queue', 'enabled')) {
            // Won't be able to background it.
            $verify = 'sync';
        }
        if ($verify == 'async') {
            $sub->scheduleVerify($mode, $token);
            header('HTTP/1.1 202 Accepted');
        } else {
            $sub->verify($mode, $token);
            header('HTTP/1.1 204 No Content');
        }
    }

    /**
     * Check whether the given URL represents one of our canonical
     * user or group Atom feeds.
     *
     * @param string $feed URL
     * @return boolean true if it matches
     */
    function recognizedFeed($feed)
    {
        $matches = array();
        if (preg_match('!/(\d+)\.atom$!', $feed, $matches)) {
            $id = $matches[1];
            $params = array('id' => $id, 'format' => 'atom');
            $userFeed = common_local_url('ApiTimelineUser', $params);
            $groupFeed = common_local_url('ApiTimelineGroup', $params);

            if ($feed == $userFeed) {
                $user = User::getKV('id', $id);
                if (!$user) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintt(_m('Invalid hub.topic "%s". User does not exist.'),$feed));
                } else {
                    return true;
                }
            }
            if ($feed == $groupFeed) {
                $user = User_group::getKV('id', $id);
                if (!$user) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintf(_m('Invalid hub.topic "%s". Group does not exist.'),$feed));
                } else {
                    return true;
                }
            }
        } else if (preg_match('!/(\d+)/lists/(\d+)/statuses\.atom$!', $feed, $matches)) {
            $user = $matches[1];
            $id = $matches[2];
            $params = array('user' => $user, 'id' => $id, 'format' => 'atom');
            $listFeed = common_local_url('ApiTimelineList', $params);

            if ($feed == $listFeed) {
                $list = Profile_list::getKV('id', $id);
                $user = User::getKV('id', $user);
                if (!$list || !$user || $list->tagger != $user->id) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintf(_m('Invalid hub.topic %s; list does not exist.'),$feed));
                } else {
                    return true;
                }
            }
            common_log(LOG_DEBUG, "Not a user, group or people tag feed? $feed $userFeed $groupFeed $listFeed");
        }
        common_log(LOG_DEBUG, "LOST $feed");
        return false;
    }

    /**
     * Grab and validate a URL from POST parameters.
     * @throws ClientException for malformed or non-http/https URLs
     */
    protected function argUrl($arg)
    {
        $url = $this->arg($arg);
        $params = array('domain_check' => false, // otherwise breaks my local tests :P
                        'allowed_schemes' => array('http', 'https'));
        if (Validate::uri($url, $params)) {
            return $url;
        } else {
            // TRANS: Client exception.
            // TRANS: %1$s is this argument to the method this exception occurs in, %2$s is a URL.
            throw new ClientException(sprintf(_m('Invalid URL passed for %1$s: "%2$s"'),$arg,$url));
        }
    }

    /**
     * Get HubSub subscription record for a given feed & subscriber.
     *
     * @param string $feed
     * @param string $callback
     * @return mixed HubSub or false
     */
    protected function getSub($feed, $callback)
    {
        return HubSub::getKV($feed, $callback);
    }
}

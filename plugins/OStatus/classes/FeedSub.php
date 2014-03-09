<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

/*
PuSH subscription flow:

    $profile->subscribe()
        sends a sub request to the hub...

    main/push/callback
        hub sends confirmation back to us via GET
        We verify the request, then echo back the challenge.
        On our end, we save the time we subscribed and the lease expiration

    main/push/callback
        hub sends us updates via POST

*/
class FeedDBException extends FeedSubException
{
    public $obj;

    function __construct($obj)
    {
        parent::__construct('Database insert failure');
        $this->obj = $obj;
    }
}

/**
 * FeedSub handles low-level PubHubSubbub (PuSH) subscriptions.
 * Higher-level behavior building OStatus stuff on top is handled
 * under Ostatus_profile.
 */
class FeedSub extends Managed_DataObject
{
    public $__table = 'feedsub';

    public $id;
    public $uri;

    // PuSH subscription data
    public $huburi;
    public $secret;
    public $sub_state; // subscribe, active, unsubscribe, inactive, nohub
    public $sub_start;
    public $sub_end;
    public $last_update;

    public $created;
    public $modified;

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'FeedSub local unique id'),
                'uri' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'FeedSub uri'),
                'huburi' => array('type' => 'text', 'description' => 'FeedSub hub-uri'),
                'secret' => array('type' => 'text', 'description' => 'FeedSub stored secret'),
                'sub_state' => array('type' => 'enum("subscribe","active","unsubscribe","inactive","nohub")', 'not null' => true, 'description' => 'subscription state'),
                'sub_start' => array('type' => 'datetime', 'description' => 'subscription start'),
                'sub_end' => array('type' => 'datetime', 'description' => 'subscription end'),
                'last_update' => array('type' => 'datetime', 'not null' => true, 'description' => 'when this record was last updated'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'feedsub_uri_key' => array('uri'),
            ),
        );
    }

    /**
     * Get the feed uri (http/https)
     */
    public function getUri()
    {
        if (empty($this->uri)) {
            throw new ServerException('No URI for FeedSub entry');
        }
        return $this->uri;
    }

    /**
     * Do we have a hub? Then we are a PuSH feed.
     * https://en.wikipedia.org/wiki/PubSubHubbub
     *
     * If huburi is empty, then doublecheck that we are not using
     * a fallback hub. If there is a fallback hub, it is only if the
     * sub_state is "nohub" that we assume it's not a PuSH feed.
     */
    public function isPuSH()
    {
        if (empty($this->huburi)
                && (!common_config('feedsub', 'fallback_hub')
                    || $this->sub_state === 'nohub')) {
                // Here we have no huburi set. Also, either there is no 
                // fallback hub configured or sub_state is "nohub".
            return false;
        }
        return true;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localProfile()
    {
        if ($this->profile_id) {
            return Profile::getKV('id', $this->profile_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        if ($this->group_id) {
            return User_group::getKV('id', $this->group_id);
        }
        return null;
    }

    /**
     * @param string $feeduri
     * @return FeedSub
     * @throws FeedSubException if feed is invalid or lacks PuSH setup
     */
    public static function ensureFeed($feeduri)
    {
        $current = self::getKV('uri', $feeduri);
        if ($current instanceof FeedSub) {
            return $current;
        }

        $discover = new FeedDiscovery();
        $discover->discoverFromFeedURL($feeduri);

        $huburi = $discover->getHubLink();
        if (!$huburi && !common_config('feedsub', 'fallback_hub')) {
            throw new FeedSubNoHubException();
        }

        $feedsub = new FeedSub();
        $feedsub->uri = $feeduri;
        $feedsub->huburi = $huburi;
        $feedsub->sub_state = 'inactive';

        $feedsub->created = common_sql_now();
        $feedsub->modified = common_sql_now();

        $result = $feedsub->insert();
        if ($result === false) {
            throw new FeedDBException($feedsub);
        }

        return $feedsub;
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe()
    {
        if ($this->sub_state && $this->sub_state != 'inactive') {
            common_log(LOG_WARNING, "Attempting to (re)start PuSH subscription to {$this->uri} in unexpected state {$this->sub_state}");
        }

        if (!Event::handle('FeedSubscribe', array($this))) {
            // A plugin handled it
            return true;
        }

        if (empty($this->huburi)) {
            if (common_config('feedsub', 'fallback_hub')) {
                // No native hub on this feed?
                // Use our fallback hub, which handles polling on our behalf.
            } else if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                // We'll never actually get updates in this mode.
                return true;
            } else {
                // TRANS: Server exception.
                throw new ServerException(_m('Attempting to start PuSH subscription for feed with no hub.'));
            }
        }

        return $this->doSubscribe('subscribe');
    }

    /**
     * Send a PuSH unsubscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     * Warning: this will cancel the subscription even if someone else in
     * the system is using it. Most callers will want garbageCollect() instead,
     * which confirms there's no uses left.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function unsubscribe() {
        if ($this->sub_state != 'active') {
            common_log(LOG_WARNING, "Attempting to (re)end PuSH subscription to {$this->uri} in unexpected state {$this->sub_state}");
        }

        if (!Event::handle('FeedUnsubscribe', array($this))) {
            // A plugin handled it
            return true;
        }

        if (empty($this->huburi)) {
            if (common_config('feedsub', 'fallback_hub')) {
                // No native hub on this feed?
                // Use our fallback hub, which handles polling on our behalf.
            } else if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                // We'll never actually get updates in this mode.
                return true;
            } else {
                // TRANS: Server exception.
                throw new ServerException(_m('Attempting to end PuSH subscription for feed with no hub.'));
            }
        }

        return $this->doSubscribe('unsubscribe');
    }

    /**
     * Check if there are any active local uses of this feed, and if not then
     * make sure it's inactive, unsubscribing if necessary.
     *
     * @return boolean true if the subscription is now inactive, false if still active.
     */
    public function garbageCollect()
    {
        if ($this->sub_state == '' || $this->sub_state == 'inactive') {
            // No active PuSH subscription, we can just leave it be.
            return true;
        } else {
            // PuSH subscription is either active or in an indeterminate state.
            // Check if we're out of subscribers, and if so send an unsubscribe.
            $count = 0;
            Event::handle('FeedSubSubscriberCount', array($this, &$count));

            if ($count) {
                common_log(LOG_INFO, __METHOD__ . ': ok, ' . $count . ' user(s) left for ' . $this->uri);
                return false;
            } else {
                common_log(LOG_INFO, __METHOD__ . ': unsubscribing, no users left for ' . $this->uri);
                return $this->unsubscribe();
            }
        }
    }

    static public function renewalCheck()
    {
        $fs = new FeedSub();
        // the "" empty string check is because we historically haven't saved unsubscribed feeds as NULL
        $fs->whereAdd('sub_end IS NOT NULL AND sub_end!="" AND sub_end < NOW() - INTERVAL 1 day');
        if (!$fs->find()) { // find can be both false and 0, depending on why nothing was found
            throw new NoResultException($fs);
        }
        return $fs;
    }

    public function renew()
    {
        $this->subscribe();
    }

    /**
     * Setting to subscribe means it is _waiting_ to become active. This
     * cannot be done in a transaction because there is a chance that the
     * remote script we're calling (as in the case of PuSHpress) performs
     * the lookup _while_ we're POSTing data, which means the transaction
     * never completes (PushcallbackAction gets an 'inactive' state).
     *
     * @return boolean  true on successful sub/unsub, false on failure
     */
    protected function doSubscribe($mode)
    {
        $orig = clone($this);
        if ($mode == 'subscribe') {
            $this->secret = common_random_hexstr(32);
        }
        $this->sub_state = $mode;
        $this->update($orig);
        unset($orig);

        try {
            $callback = common_local_url('pushcallback', array('feed' => $this->id));
            $headers = array('Content-Type: application/x-www-form-urlencoded');
            $post = array('hub.mode' => $mode,
                          'hub.callback' => $callback,
                          'hub.verify' => 'async',  // TODO: deprecated, remove when noone uses PuSH <0.4 (only 'async' method used there)
                          'hub.verify_token' => 'Deprecated-since-PuSH-0.4', // TODO: rm!

                          'hub.secret' => $this->secret,
                          'hub.topic' => $this->uri);
            $client = new HTTPClient();
            if ($this->huburi) {
                $hub = $this->huburi;
            } else {
                if (common_config('feedsub', 'fallback_hub')) {
                    $hub = common_config('feedsub', 'fallback_hub');
                    if (common_config('feedsub', 'hub_user')) {
                        $u = common_config('feedsub', 'hub_user');
                        $p = common_config('feedsub', 'hub_pass');
                        $client->setAuth($u, $p);
                    }
                } else {
                    throw new FeedSubException('WTF?');
                }
            }
            $response = $client->post($hub, $headers, $post);
            $status = $response->getStatus();
            if ($status == 202) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok, awaiting verification callback');
                return true;
            } else if ($status >= 200 && $status < 300) {
                common_log(LOG_ERR, __METHOD__ . ": sub req returned unexpected HTTP $status: " . $response->getBody());
            } else {
                common_log(LOG_ERR, __METHOD__ . ": sub req failed with HTTP $status: " . $response->getBody());
            }
        } catch (Exception $e) {
            // wtf!
            common_log(LOG_ERR, __METHOD__ . ": error \"{$e->getMessage()}\" hitting hub $this->huburi subscribing to $this->uri");

            $orig = clone($this);
            $this->sub_state = 'inactive';
            $this->update($orig);
            unset($orig);
        }
        return false;
    }

    /**
     * Save PuSH subscription confirmation.
     * Sets approximate lease start and end times and finalizes state.
     *
     * @param int $lease_seconds provided hub.lease_seconds parameter, if given
     */
    public function confirmSubscribe($lease_seconds)
    {
        $original = clone($this);

        $this->sub_state = 'active';
        $this->sub_start = common_sql_date(time());
        if ($lease_seconds > 0) {
            $this->sub_end = common_sql_date(time() + $lease_seconds);
        } else {
            $this->sub_end = null;  // Backwards compatibility to StatusNet (PuSH <0.4 supported permanent subs)
        }
        $this->modified = common_sql_now();

        return $this->update($original);
    }

    /**
     * Save PuSH unsubscription confirmation.
     * Wipes active PuSH sub info and resets state.
     */
    public function confirmUnsubscribe()
    {
        $original = clone($this);

        // @fixme these should all be null, but DB_DataObject doesn't save null values...?????
        $this->secret = '';
        $this->sub_state = '';
        $this->sub_start = '';
        $this->sub_end = '';
        $this->modified = common_sql_now();

        return $this->update($original);
    }

    /**
     * Accept updates from a PuSH feed. If validated, this object and the
     * feed (as a DOMDocument) will be passed to the StartFeedSubHandleFeed
     * and EndFeedSubHandleFeed events for processing.
     *
     * Not guaranteed to be running in an immediate POST context; may be run
     * from a queue handler.
     *
     * Side effects: the feedsub record's lastupdate field will be updated
     * to the current time (not published time) if we got a legit update.
     *
     * @param string $post source of Atom or RSS feed
     * @param string $hmac X-Hub-Signature header, if present
     */
    public function receive($post, $hmac)
    {
        common_log(LOG_INFO, __METHOD__ . ": packet for \"$this->uri\"! $hmac $post");

        if ($this->sub_state != 'active') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH for inactive feed $this->uri (in state '$this->sub_state')");
            return;
        }

        if ($post === '') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring empty post");
            return;
        }

        if (!$this->validatePushSig($post, $hmac)) {
            // Per spec we silently drop input with a bad sig,
            // while reporting receipt to the server.
            return;
        }

        $feed = new DOMDocument();
        if (!$feed->loadXML($post)) {
            // @fixme might help to include the err message
            common_log(LOG_ERR, __METHOD__ . ": ignoring invalid XML");
            return;
        }

        $orig = clone($this);
        $this->last_update = common_sql_now();
        $this->update($orig);

        Event::handle('StartFeedSubReceive', array($this, $feed));
        Event::handle('EndFeedSubReceive', array($this, $feed));
    }

    /**
     * Validate the given Atom chunk and HMAC signature against our
     * shared secret that was set up at subscription time.
     *
     * If we don't have a shared secret, there should be no signature.
     * If we we do, our the calculated HMAC should match theirs.
     *
     * @param string $post raw XML source as POSTed to us
     * @param string $hmac X-Hub-Signature HTTP header value, or empty
     * @return boolean true for a match
     */
    protected function validatePushSig($post, $hmac)
    {
        if ($this->secret) {
            if (preg_match('/^sha1=([0-9a-fA-F]{40})$/', $hmac, $matches)) {
                $their_hmac = strtolower($matches[1]);
                $our_hmac = hash_hmac('sha1', $post, $this->secret);
                if ($their_hmac === $our_hmac) {
                    return true;
                }
                if (common_config('feedsub', 'debug')) {
                    $tempfile = tempnam(sys_get_temp_dir(), 'feedsub-receive');
                    if ($tempfile) {
                        file_put_contents($tempfile, $post);
                    }
                    common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bad SHA-1 HMAC: got $their_hmac, expected $our_hmac for feed $this->uri on $this->huburi; saved to $tempfile");
                } else {
                    common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bad SHA-1 HMAC: got $their_hmac, expected $our_hmac for feed $this->uri on $this->huburi");
                }
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bogus HMAC '$hmac'");
            }
        } else {
            if (empty($hmac)) {
                return true;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with unexpected HMAC '$hmac'");
            }
        }
        return false;
    }
}

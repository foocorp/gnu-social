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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * PuSH feed subscription record
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubSub extends Managed_DataObject
{
    public $__table = 'hubsub';

    public $hashkey; // sha1(topic . '|' . $callback); (topic, callback) key is too long for myisam in utf8
    public $topic;
    public $callback;
    public $secret;
    public $lease;
    public $sub_start;
    public $sub_end;
    public $created;
    public $modified;

    protected static function hashkey($topic, $callback)
    {
        return sha1($topic . '|' . $callback);
    }

    public static function getByHashkey($topic, $callback)
    {
        return self::getKV('hashkey', self::hashkey($topic, $callback));
    }

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'hashkey' => array('type' => 'char', 'not null' => true, 'length' => 40, 'description' => 'HubSub hashkey'),
                'topic' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'HubSub topic'),
                'callback' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'HubSub callback'),
                'secret' => array('type' => 'text', 'description' => 'HubSub stored secret'),
                'lease' => array('type' => 'int', 'not null' => true, 'description' => 'HubSub leasetime'),
                'sub_start' => array('type' => 'datetime', 'description' => 'subscription start'),
                'sub_end' => array('type' => 'datetime', 'description' => 'subscription end'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('hashkey'),
            'indexes' => array(
                'hubsub_topic_idx' => array('topic'),
            ),
        );
    }

    /**
     * Validates a requested lease length, sets length plus
     * subscription start & end dates.
     *
     * Does not save to database -- use before insert() or update().
     *
     * @param int $length in seconds
     */
    function setLease($length)
    {
        assert(is_int($length));

        $min = 86400;
        $max = 86400 * 30;

        if ($length == 0) {
            // We want to garbage collect dead subscriptions!
            $length = $max;
        } elseif( $length < $min) {
            $length = $min;
        } else if ($length > $max) {
            $length = $max;
        }

        $this->lease = $length;
        $this->start_sub = common_sql_now();
        $this->end_sub = common_sql_date(time() + $length);
    }

    /**
     * Schedule a future verification ping to the subscriber.
     * If queues are disabled, will be immediate.
     *
     * @param string $mode 'subscribe' or 'unsubscribe'
     * @param string $token hub.verify_token value, if provided by client
     */
    function scheduleVerify($mode, $token=null, $retries=null)
    {
        if ($retries === null) {
            $retries = intval(common_config('ostatus', 'hub_retries'));
        }
        $data = array('sub' => clone($this),
                      'mode' => $mode,
                      'token' => $token,    // let's put it in there if remote uses PuSH <0.4
                      'retries' => $retries);
        $qm = QueueManager::get();
        $qm->enqueue($data, 'hubconf');
    }

    /**
     * Send a verification ping to subscriber, and if confirmed apply the changes.
     * This may create, update, or delete the database record.
     *
     * @param string $mode 'subscribe' or 'unsubscribe'
     * @param string $token hub.verify_token value, if provided by client
     * @throws ClientException on failure
     */
    function verify($mode, $token=null)
    {
        assert($mode == 'subscribe' || $mode == 'unsubscribe');

        $challenge = common_random_hexstr(32);
        $params = array('hub.mode' => $mode,
                        'hub.topic' => $this->topic,
                        'hub.challenge' => $challenge);
        if ($mode == 'subscribe') {
            $params['hub.lease_seconds'] = $this->lease;
        }
        if ($token !== null) {  // TODO: deprecated in PuSH 0.4
            $params['hub.verify_token'] = $token;   // let's put it in there if remote uses PuSH <0.4
        }

        // Any existing query string parameters must be preserved
        $url = $this->callback;
        if (strpos($url, '?') !== false) {
            $url .= '&';
        } else {
            $url .= '?';
        }
        $url .= http_build_query($params, '', '&');

        $request = new HTTPClient();
        $response = $request->get($url);
        $status = $response->getStatus();

        if ($status >= 200 && $status < 300) {
            common_log(LOG_INFO, "Verified {$mode} of {$this->callback}:{$this->topic}");
        } else {
            // TRANS: Client exception. %s is a HTTP status code.
            throw new ClientException(sprintf(_m('Hub subscriber verification returned HTTP %s.'),$status));
        }

        $old = HubSub::getByHashkey($this->topic, $this->callback);
        if ($mode == 'subscribe') {
            if ($old instanceof HubSub) {
                $this->update($old);
            } else {
                $ok = $this->insert();
            }
        } else if ($mode == 'unsubscribe') {
            if ($old instanceof HubSub) {
                $old->delete();
            } else {
                // That's ok, we're already unsubscribed.
            }
        }
    }

    /**
     * Insert wrapper; transparently set the hash key from topic and callback columns.
     * @return mixed success
     */
    function insert()
    {
        $this->hashkey = self::hashkey($this->topic, $this->callback);
        $this->created = common_sql_now();
        $this->modified = common_sql_now();
        return parent::insert();
    }

    /**
     * Schedule delivery of a 'fat ping' to the subscriber's callback
     * endpoint. If queues are disabled, this will run immediately.
     *
     * @param string $atom well-formed Atom feed
     * @param int $retries optional count of retries if POST fails; defaults to hub_retries from config or 0 if unset
     */
    function distribute($atom, $retries=null)
    {
        if ($retries === null) {
            $retries = intval(common_config('ostatus', 'hub_retries'));
        }

        // We dare not clone() as when the clone is discarded it'll
        // destroy the result data for the parent query.
        // @fixme use clone() again when it's safe to copy an
        // individual item from a multi-item query again.
        $sub = HubSub::getByHashkey($this->topic, $this->callback);
        $data = array('sub' => $sub,
                      'atom' => $atom,
                      'retries' => $retries);
        common_log(LOG_INFO, "Queuing PuSH: $this->topic to $this->callback");
        $qm = QueueManager::get();
        $qm->enqueue($data, 'hubout');
    }

    /**
     * Queue up a large batch of pushes to multiple subscribers
     * for this same topic update.
     *
     * If queues are disabled, this will run immediately.
     *
     * @param string $atom well-formed Atom feed
     * @param array $pushCallbacks list of callback URLs
     */
    function bulkDistribute($atom, $pushCallbacks)
    {
        $data = array('atom' => $atom,
                      'topic' => $this->topic,
                      'pushCallbacks' => $pushCallbacks);
        common_log(LOG_INFO, "Queuing PuSH batch: $this->topic to " .
                             count($pushCallbacks) . " sites");
        $qm = QueueManager::get();
        $qm->enqueue($data, 'hubprep');
    }

    /**
     * Send a 'fat ping' to the subscriber's callback endpoint
     * containing the given Atom feed chunk.
     *
     * Determination of which items to send should be done at
     * a higher level; don't just shove in a complete feed!
     *
     * @param string $atom well-formed Atom feed
     * @throws Exception (HTTP or general)
     */
    function push($atom)
    {
        $headers = array('Content-Type: application/atom+xml');
        if ($this->secret) {
            $hmac = hash_hmac('sha1', $atom, $this->secret);
            $headers[] = "X-Hub-Signature: sha1=$hmac";
        } else {
            $hmac = '(none)';
        }
        common_log(LOG_INFO, "About to push feed to $this->callback for $this->topic, HMAC $hmac");

        $request = new HTTPClient();
        $request->setBody($atom);
        $response = $request->post($this->callback, $headers);

        if ($response->isOk()) {
            return true;
        } else {
            // TRANS: Exception. %1$s is a response status code, %2$s is the body of the response.
            throw new Exception(sprintf(_m('Callback returned status: %1$s. Body: %2$s'),
                                $response->getStatus(),trim($response->getBody())));
        }
    }
}

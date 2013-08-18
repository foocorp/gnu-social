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

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array('hashkey' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'topic' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'callback' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'secret' => DB_DATAOBJECT_STR,
                     'lease' =>  DB_DATAOBJECT_INT,
                     'sub_start' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'sub_end' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('hashkey', 'char',
                                   /*size*/40,
                                   /*nullable*/false,
                                   /*key*/'PRI'),
                     new ColumnDef('topic', 'varchar',
                                   /*size*/255,
                                   /*nullable*/false,
                                   /*key*/'MUL'),
                     new ColumnDef('callback', 'varchar',
                                   255, false),
                     new ColumnDef('secret', 'text',
                                   null, true),
                     new ColumnDef('lease', 'int',
                                   null, true),
                     new ColumnDef('sub_start', 'datetime',
                                   null, true),
                     new ColumnDef('sub_end', 'datetime',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */
    function keyTypes()
    {
        return array('hashkey' => 'K');
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
                      'token' => $token,
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

        $challenge = common_good_rand(32);
        $params = array('hub.mode' => $mode,
                        'hub.topic' => $this->topic,
                        'hub.challenge' => $challenge);
        if ($mode == 'subscribe') {
            $params['hub.lease_seconds'] = $this->lease;
        }
        if ($token !== null) {
            $params['hub.verify_token'] = $token;
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
            common_log(LOG_INFO, "Verified $mode of $this->callback:$this->topic");
        } else {
            // TRANS: Client exception. %s is a HTTP status code.
            throw new ClientException(sprintf(_m('Hub subscriber verification returned HTTP %s.'),$status));
        }

        $old = HubSub::staticGet($this->topic, $this->callback);
        if ($mode == 'subscribe') {
            if ($old) {
                $this->update($old);
            } else {
                $ok = $this->insert();
            }
        } else if ($mode == 'unsubscribe') {
            if ($old) {
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
     * Update wrapper; transparently update modified column.
     * @return boolean success
     */
    function update($old=null)
    {
        $this->modified = common_sql_now();
        return parent::update($old);
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

        if (common_config('ostatus', 'local_push_bypass')) {
            // If target is a local site, bypass the web server and drop the
            // item directly into the target's input queue.
            $url = parse_url($this->callback);
            $wildcard = common_config('ostatus', 'local_wildcard');
            $site = Status_network::getFromHostname($url['host'], $wildcard);

            if ($site) {
                if ($this->secret) {
                    $hmac = 'sha1=' . hash_hmac('sha1', $atom, $this->secret);
                } else {
                    $hmac = '';
                }

                // Hack: at the moment we stick the subscription ID in the callback
                // URL so we don't have to look inside the Atom to route the subscription.
                // For now this means we need to extract that from the target URL
                // so we can include it in the data.
                $parts = explode('/', $url['path']);
                $subId = intval(array_pop($parts));

                $data = array('feedsub_id' => $subId,
                              'post' => $atom,
                              'hmac' => $hmac);
                common_log(LOG_DEBUG, "Cross-site PuSH bypass enqueueing straight to $site->nickname feed $subId");
                $qm = QueueManager::get();
                $qm->enqueue($data, 'pushin', $site->nickname);
                return;
            }
        }

        // We dare not clone() as when the clone is discarded it'll
        // destroy the result data for the parent query.
        // @fixme use clone() again when it's safe to copy an
        // individual item from a multi-item query again.
        $sub = HubSub::staticGet($this->topic, $this->callback);
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

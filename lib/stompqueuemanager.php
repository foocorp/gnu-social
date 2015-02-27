<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstract class for queue managers
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
 * @category  QueueManager
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

require_once 'Stomp.php';
require_once 'Stomp/Exception.php';

class StompQueueManager extends QueueManager
{
    protected $servers;
    protected $username;
    protected $password;
    protected $base;
    protected $control;

    protected $useTransactions;
    protected $useAcks;

    protected $sites = array();
    protected $subscriptions = array();

    protected $cons = array(); // all open connections
    protected $disconnect = array();
    protected $transaction = array();
    protected $transactionCount = array();
    protected $defaultIdx = 0;

    function __construct()
    {
        parent::__construct();
        $server = common_config('queue', 'stomp_server');
        if (is_array($server)) {
            $this->servers = $server;
        } else {
            $this->servers = array($server);
        }
        $this->username        = common_config('queue', 'stomp_username');
        $this->password        = common_config('queue', 'stomp_password');
        $this->base            = common_config('queue', 'queue_basename');
        $this->control         = common_config('queue', 'control_channel');
        $this->breakout        = common_config('queue', 'breakout');
        $this->useTransactions = common_config('queue', 'stomp_transactions');
        $this->useAcks         = common_config('queue', 'stomp_acks');
    }

    /**
     * Tell the i/o master we only need a single instance to cover
     * all sites running in this process.
     */
    public static function multiSite()
    {
        return IoManager::INSTANCE_PER_PROCESS;
    }

    /**
     * Optional; ping any running queue handler daemons with a notification
     * such as announcing a new site to handle or requesting clean shutdown.
     * This avoids having to restart all the daemons manually to update configs
     * and such.
     *
     * Currently only relevant for multi-site queue managers such as Stomp.
     *
     * @param string $event event key
     * @param string $param optional parameter to append to key
     * @return boolean success
     */
    public function sendControlSignal($event, $param='')
    {
        $message = $event;
        if ($param != '') {
            $message .= ':' . $param;
        }
        $this->_connect();
        $con = $this->cons[$this->defaultIdx];
        $result = $con->send($this->control,
                             $message,
                             array ('created' => common_sql_now()));
        if ($result) {
            $this->_log(LOG_INFO, "Sent control ping to queue daemons: $message");
            return true;
        } else {
            $this->_log(LOG_ERR, "Failed sending control ping to queue daemons: $message");
            return false;
        }
    }

    /**
     * Saves an object into the queue item table.
     *
     * @param mixed $object
     * @param string $queue
     * @param string $siteNickname optional override to drop into another site's queue
     *
     * @return boolean true on success
     * @throws StompException on connection or send error
     */
    public function enqueue($object, $queue, $siteNickname=null)
    {
        $this->_connect();
        if (common_config('queue', 'stomp_enqueue_on')) {
            // We're trying to force all writes to a single server.
            // WARNING: this might do odd things if that server connection dies.
            $idx = array_search(common_config('queue', 'stomp_enqueue_on'),
                                $this->servers);
            if ($idx === false) {
                common_log(LOG_ERR, 'queue stomp_enqueue_on setting does not match our server list.');
                $idx = $this->defaultIdx;
            }
        } else {
            $idx = $this->defaultIdx;
        }
        return $this->_doEnqueue($object, $queue, $idx, $siteNickname);
    }

    /**
     * Saves a notice object reference into the queue item table
     * on the given connection.
     *
     * @return boolean true on success
     * @throws StompException on connection or send error
     */
    protected function _doEnqueue($object, $queue, $idx, $siteNickname=null)
    {
        $rep = $this->logrep($object);
        $envelope = array('site' => $siteNickname ? $siteNickname : common_config('site', 'nickname'),
                          'handler' => $queue,
                          'payload' => $this->encode($object));
        $msg = serialize($envelope);

        $props = array('created' => common_sql_now());
        if ($this->isPersistent($queue)) {
            $props['persistent'] = 'true';
        }

        $con = $this->cons[$idx];
        $host = $con->getServer();
        $target = $this->queueName($queue);
        $result = $con->send($target, $msg, $props);

        if (!$result) {
            $this->_log(LOG_ERR, "Error sending $rep to $queue queue on $host $target");
            return false;
        }

        $this->_log(LOG_DEBUG, "complete remote queueing $rep for $queue on $host $target");
        $this->stats('enqueued', $queue);
        return true;
    }

    /**
     * Determine whether messages to this queue should be marked as persistent.
     * Actual persistent storage depends on the queue server's configuration.
     * @param string $queue
     * @return bool
     */
    protected function isPersistent($queue)
    {
        $mode = common_config('queue', 'stomp_persistent');
        if (is_array($mode)) {
            return in_array($queue, $mode);
        } else {
            return (bool)$mode;
        }
    }

    /**
     * Send any sockets we're listening on to the IO manager
     * to wait for input.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        $sockets = array();
        foreach ($this->cons as $con) {
            if ($con) {
                $sockets[] = $con->getSocket();
            }
        }
        return $sockets;
    }

    /**
     * Get the Stomp connection object associated with the given socket.
     * @param resource $socket
     * @return int index into connections list
     * @throws Exception
     */
    protected function connectionFromSocket($socket)
    {
        foreach ($this->cons as $i => $con) {
            if ($con && $con->getSocket() === $socket) {
                return $i;
            }
        }
        throw new Exception(__CLASS__ . " asked to read from unrecognized socket");
    }

    /**
     * We've got input to handle on our socket!
     * Read any waiting Stomp frame(s) and process them.
     *
     * @param resource $socket
     * @return boolean ok on success
     */
    public function handleInput($socket)
    {
        $idx = $this->connectionFromSocket($socket);
        $con = $this->cons[$idx];
        $host = $con->getServer();
        $this->defaultIdx = $idx;

        $ok = true;
        try {
            $frames = $con->readFrames();
        } catch (StompException $e) {
            $this->_log(LOG_ERR, "Lost connection to $host: " . $e->getMessage());
            fclose($socket); // ???
            $this->cons[$idx] = null;
            $this->transaction[$idx] = null;
            $this->disconnect[$idx] = time();
            return false;
        }
        foreach ($frames as $frame) {
            $dest = $frame->headers['destination'];
            if ($dest == $this->control) {
                if (!$this->handleControlSignal($frame)) {
                    // We got a control event that requests a shutdown;
                    // close out and stop handling anything else!
                    break;
                }
            } else {
                $ok = $this->handleItem($frame) && $ok;
            }
            $this->ack($idx, $frame);
            $this->commit($idx);
            $this->begin($idx);
        }
        return $ok;
    }

    /**
     * Attempt to reconnect in background if we lost a connection.
     */
    function idle()
    {
        $now = time();
        foreach ($this->cons as $idx => $con) {
            if (empty($con)) {
                $age = $now - $this->disconnect[$idx];
                if ($age >= 60) {
                    $this->_reconnect($idx);
                }
            }
        }
        return true;
    }

    /**
     * Initialize our connection and subscribe to all the queues
     * we're going to need to handle... If multiple queue servers
     * are configured for failover, we'll listen to all of them.
     *
     * Side effects: in multi-site mode, may reset site configuration.
     *
     * @param IoMaster $master process/event controller
     * @return bool return false on failure
     */
    public function start($master)
    {
        parent::start($master);
        $this->_connectAll();

        foreach ($this->cons as $i => $con) {
            if ($con) {
                $this->doSubscribe($con);
                $this->begin($i);
            }
        }
        return true;
    }

    /**
     * Close out any active connections.
     *
     * @return bool return false on failure
     */
    public function finish()
    {
        // If there are any outstanding delivered messages we haven't processed,
        // free them for another thread to take.
        foreach ($this->cons as $i => $con) {
            if ($con) {
                $this->rollback($i);
                $con->disconnect();
                $this->cons[$i] = null;
            }
        }
        return true;
    }

    /**
     * Lazy open a single connection to Stomp queue server.
     * If multiple servers are configured, we let the Stomp client library
     * worry about finding a working connection among them.
     */
    protected function _connect()
    {
        if (empty($this->cons)) {
            $list = $this->servers;
            if (count($list) > 1) {
                shuffle($list); // Randomize to spread load
                $url = 'failover://(' . implode(',', $list) . ')';
            } else {
                $url = $list[0];
            }
            $con = $this->_doConnect($url);
            $this->cons = array($con);
            $this->transactionCount = array(0);
            $this->transaction = array(null);
            $this->disconnect = array(null);
        }
    }

    /**
     * Lazy open connections to all Stomp servers, if in manual failover
     * mode. This means the queue servers don't speak to each other, so
     * we have to listen to all of them to make sure we get all events.
     */
    protected function _connectAll()
    {
        if (!common_config('queue', 'stomp_manual_failover')) {
            return $this->_connect();
        }
        if (empty($this->cons)) {
            $this->cons = array();
            $this->transactionCount = array();
            $this->transaction = array();
            foreach ($this->servers as $idx => $server) {
                try {
                    $this->cons[] = $this->_doConnect($server);
                    $this->disconnect[] = null;
                } catch (Exception $e) {
                    // s'okay, we'll live
                    $this->cons[] = null;
                    $this->disconnect[] = time();
                }
                $this->transactionCount[] = 0;
                $this->transaction[] = null;
            }
            if (empty($this->cons)) {
                throw new ServerException("No queue servers reachable...");
                return false;
            }
        }
    }

    /**
     * Attempt to manually reconnect to the Stomp server for the given
     * slot. If successful, set up our subscriptions on it.
     */
    protected function _reconnect($idx)
    {
        try {
            $con = $this->_doConnect($this->servers[$idx]);
        } catch (Exception $e) {
            $this->_log(LOG_ERR, $e->getMessage());
            $con = null;
        }
        if ($con) {
            $this->cons[$idx] = $con;
            $this->disconnect[$idx] = null;

            $this->doSubscribe($con);
            $this->begin($idx);
        } else {
            // Try again later...
            $this->disconnect[$idx] = time();
        }
    }

    protected function _doConnect($server)
    {
        $this->_log(LOG_INFO, "Connecting to '$server' as '$this->username'...");
        $con = new LiberalStomp($server);

        if ($con->connect($this->username, $this->password)) {
            $this->_log(LOG_INFO, "Connected.");
        } else {
            $this->_log(LOG_ERR, 'Failed to connect to queue server');
            throw new ServerException('Failed to connect to queue server');
        }

        return $con;
    }

    /**
     * Set up all our raw queue subscriptions on the given connection
     * @param LiberalStomp $con
     */
    protected function doSubscribe(LiberalStomp $con)
    {
        $host = $con->getServer();
        foreach ($this->subscriptions() as $sub) {
            $this->_log(LOG_INFO, "Subscribing to $sub on $host");
            $con->subscribe($sub);
        }
    }
    
    /**
     * Grab a full list of stomp-side queue subscriptions.
     * Will include:
     *  - control broadcast channel
     *  - shared group queues for active groups
     *  - per-handler and per-site breakouts from $config['queue']['breakout']
     *    that are rooted in the active groups.
     *
     * @return array of strings
     */
    protected function subscriptions()
    {
        $subs = array();
        $subs[] = $this->control;

        foreach ($this->activeGroups as $group) {
            $subs[] = $this->base . $group;
        }

        foreach ($this->breakout as $spec) {
            $parts = explode('/', $spec);
            if (count($parts) < 2 || count($parts) > 3) {
                common_log(LOG_ERR, "Bad queue breakout specifier $spec");
            }
            if (in_array($parts[0], $this->activeGroups)) {
                $subs[] = $this->base . $spec;
            }
        }
        return array_unique($subs);
    }

    /**
     * Handle and acknowledge an event that's come in through a queue.
     *
     * If the queue handler reports failure, the message is requeued for later.
     * Missing notices or handler classes will drop the message.
     *
     * Side effects: in multi-site mode, may reset site configuration to
     * match the site that queued the event.
     *
     * @param StompFrame $frame
     * @return bool success
     */
    protected function handleItem($frame)
    {
        $host = $this->cons[$this->defaultIdx]->getServer();
        $message = unserialize($frame->body);

        if ($message === false) {
            $this->_log(LOG_ERR, "Can't unserialize frame: {$frame->body}");
            $this->_log(LOG_ERR, "Unserializable frame length: " . strlen($frame->body));
            return false;
        }

        $site = $message['site'];
        $queue = $message['handler'];

        if ($this->isDeadletter($frame, $message)) {
            $this->stats('deadletter', $queue);
	        return false;
        }

        // @fixme detect failing site switches
        $this->switchSite($site);

        try {
            $item = $this->decode($message['payload']);
        } catch (Exception $e) {
            $this->_log(LOG_ERR, "Skipping empty or deleted item in queue $queue from $host");
            $this->stats('baditem', $queue);
            return false;
        }
        $info = $this->logrep($item) . " posted at " .
                $frame->headers['created'] . " in queue $queue from $host";
        $this->_log(LOG_DEBUG, "Dequeued $info");

        $handler = $this->getHandler($queue);
        if (!$handler) {
            $this->_log(LOG_ERR, "Missing handler class; skipping $info");
            $this->stats('badhandler', $queue);
            return false;
        }

        try {
            $ok = $handler->handle($item);
        } catch (Exception $e) {
            $this->_log(LOG_ERR, "Exception on queue $queue: " . $e->getMessage());
            $ok = false;
        }

        if ($ok) {
            $this->_log(LOG_INFO, "Successfully handled $info");
            $this->stats('handled', $queue);
        } else {
            $this->_log(LOG_WARNING, "Failed handling $info");
            // Requeing moves the item to the end of the line for its next try.
            // @fixme add a manual retry count
            $this->enqueue($item, $queue);
            $this->stats('requeued', $queue);
        }

        return $ok;
    }

    /**
     * Check if a redelivered message has been run through enough
     * that we're going to give up on it.
     *
     * @param StompFrame $frame
     * @param array $message unserialized message body
     * @return boolean true if we should discard
     */
    protected function isDeadLetter($frame, $message)
    {
        if (isset($frame->headers['redelivered']) && $frame->headers['redelivered'] == 'true') {
	        // Message was redelivered, possibly indicating a previous failure.
            $msgId = $frame->headers['message-id'];
            $site = $message['site'];
            $queue = $message['handler'];
	        $msgInfo = "message $msgId for $site in queue $queue";

	        $deliveries = $this->incDeliveryCount($msgId);
	        if ($deliveries > common_config('queue', 'max_retries')) {
		        $info = "DEAD-LETTER FILE: Gave up after retry $deliveries on $msgInfo";

		        $outdir = common_config('queue', 'dead_letter_dir');
		        if ($outdir) {
    		        $filename = $outdir . "/$site-$queue-" . rawurlencode($msgId);
    		        $info .= ": dumping to $filename";
    		        file_put_contents($filename, $message['payload']);
		        }

		        common_log(LOG_ERR, $info);
		        return true;
	        } else {
	            common_log(LOG_INFO, "retry $deliveries on $msgInfo");
	        }
        }
        return false;
    }

    /**
     * Update count of times we've re-encountered this message recently,
     * triggered when we get a message marked as 'redelivered'.
     *
     * Requires a CLI-friendly cache configuration.
     *
     * @param string $msgId message-id header from message
     * @return int number of retries recorded
     */
    function incDeliveryCount($msgId)
    {
	    $count = 0;
	    $cache = Cache::instance();
	    if ($cache) {
		    $key = 'statusnet:stomp:message-retries:' . $msgId;
		    $count = $cache->increment($key);
		    if (!$count) {
			    $count = 1;
			    $cache->set($key, $count, null, 3600);
			    $got = $cache->get($key);
		    }
	    }
	    return $count;
    }

    /**
     * Process a control signal broadcast.
     *
     * @param int $idx connection index
     * @param array $frame Stomp frame
     * @return bool true to continue; false to stop further processing.
     */
    protected function handleControlSignal($idx, $frame)
    {
        $message = trim($frame->body);
        if (strpos($message, ':') !== false) {
            list($event, $param) = explode(':', $message, 2);
        } else {
            $event = $message;
            $param = '';
        }

        $shutdown = false;

        if ($event == 'shutdown') {
            $this->master->requestShutdown();
            $shutdown = true;
        } else if ($event == 'restart') {
            $this->master->requestRestart();
            $shutdown = true;
        } else if ($event == 'update') {
            $this->updateSiteConfig($param);
        } else {
            $this->_log(LOG_ERR, "Ignoring unrecognized control message: $message");
        }
        return $shutdown;
    }

    /**
     * Switch site, if necessary, and reset current handler assignments
     * @param string $site
     */
    function switchSite($site)
    {
        if ($site != GNUsocial::currentSite()) {
            $this->stats('switch');
            GNUsocial::switchSite($site);
            $this->initialize();
        }
    }

    /**
     * (Re)load runtime configuration for a given site by nickname,
     * triggered by a broadcast to the 'statusnet-control' topic.
     *
     * Configuration changes in database should update, but config
     * files might not.
     *
     * @param array $frame Stomp frame
     * @return bool true to continue; false to stop further processing.
     */
    protected function updateSiteConfig($nickname)
    {
        $sn = Status_network::getKV('nickname', $nickname);
        if ($sn) {
            $this->switchSite($nickname);
            if (!in_array($nickname, $this->sites)) {
                $this->addSite();
            }
            $this->stats('siteupdate');
        } else {
            $this->_log(LOG_ERR, "Ignoring ping for unrecognized new site $nickname");
        }
    }

    /**
     * Combines the queue_basename from configuration with the
     * group name for this queue to give eg:
     *
     * /queue/statusnet/main
     * /queue/statusnet/main/distrib
     * /queue/statusnet/xmpp/xmppout/site01
     *
     * @param string $queue
     * @return string
     */
    protected function queueName($queue)
    {
        $group = $this->queueGroup($queue);
        $site = GNUsocial::currentSite();

        $specs = array("$group/$queue/$site",
                       "$group/$queue");
        foreach ($specs as $spec) {
            if (in_array($spec, $this->breakout)) {
                return $this->base . $spec;
            }
        }
        return $this->base . $group;
    }

    /**
     * Get the breakout mode for the given queue on the current site.
     *
     * @param string $queue
     * @return string one of 'shared', 'handler', 'site'
     */
    protected function breakoutMode($queue)
    {
        $breakout = common_config('queue', 'breakout');
        if (isset($breakout[$queue])) {
            return $breakout[$queue];
        } else if (isset($breakout['*'])) {
            return $breakout['*'];
        } else {
            return 'shared';
        }
    }

    protected function begin($idx)
    {
        if ($this->useTransactions) {
            if (!empty($this->transaction[$idx])) {
                throw new Exception("Tried to start transaction in the middle of a transaction");
            }
            $this->transactionCount[$idx]++;
            $this->transaction[$idx] = $this->master->id . '-' . $this->transactionCount[$idx] . '-' . time();
            $this->cons[$idx]->begin($this->transaction[$idx]);
        }
    }

    protected function ack($idx, $frame)
    {
        if ($this->useAcks) {
            if ($this->useTransactions) {
                if (empty($this->transaction[$idx])) {
                    throw new Exception("Tried to ack but not in a transaction");
                }
                $this->cons[$idx]->ack($frame, $this->transaction[$idx]);
            } else {
                $this->cons[$idx]->ack($frame);
            }
        }
    }

    protected function commit($idx)
    {
        if ($this->useTransactions) {
            if (empty($this->transaction[$idx])) {
                throw new Exception("Tried to commit but not in a transaction");
            }
            $this->cons[$idx]->commit($this->transaction[$idx]);
            $this->transaction[$idx] = null;
        }
    }

    protected function rollback($idx)
    {
        if ($this->useTransactions) {
            if (empty($this->transaction[$idx])) {
                throw new Exception("Tried to rollback but not in a transaction");
            }
            $this->cons[$idx]->commit($this->transaction[$idx]);
            $this->transaction[$idx] = null;
        }
    }
}


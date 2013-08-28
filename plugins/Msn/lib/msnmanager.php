<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * MSN background connection manager for MSN-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has MSN enabled.
 */
class MsnManager extends ImManager {
    public $conn = null;
    protected $lastPing = null;
    protected $pingInterval;

    /**
     * Initialise connection to server.
     *
     * @return boolean true on success
     */
    public function start($master) {
        if (parent::start($master)) {
            $this->requeue_waiting_messages();
            $this->connect();
            return true;
        } else {
            return false;
        }
    }

    /**
    * Return any open sockets that the run loop should listen
    * for input on.
    *
    * @return array Array of socket resources
    */
    public function getSockets() {
        $this->connect();
        if ($this->conn) {
            return $this->conn->getSockets();
        } else {
            return array();
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     * Send keepalive pings to server.
     *
     * @return void
     */
    public function idle($timeout = 0) {
        if (empty($this->lastPing) || time() - $this->lastPing > $this->pingInterval) {
            $this->send_ping();
        }
    }

    /**
     * Message pump is triggered on socket input, so we only need an idle()
     * call often enough to trigger our outgoing pings.
     */
    public function timeout() {
        return $this->pingInterval;
    }

    /**
     * Process MSN events that have come in over the wire.
     *
     * @param resource $socket Socket ready
     * @return void
     */
    public function handleInput($socket) {
        common_log(LOG_DEBUG, 'Servicing the MSN queue.');
        $this->stats('msn_process');
        $this->conn->receive();
    }

    /**
    * Initiate connection
    *
    * @return void
    */
    public function connect() {
        if (!$this->conn) {
            $this->conn = new MSN(
                array(
                    'user' => $this->plugin->user,
                    'password' => $this->plugin->password,
                    'alias' => $this->plugin->nickname,
                    // TRANS: MSN bot status message.
                    'psm' => _m('Send me a message to post a notice'),
                    'debug' => false
                )
            );
            $this->conn->registerHandler('IMin', array($this, 'handle_msn_message'));
            $this->conn->registerHandler('SessionReady', array($this, 'handle_session_ready'));
            $this->conn->registerHandler('Pong', array($this, 'update_ping_time'));
            $this->conn->registerHandler('ConnectFailed', array($this, 'handle_connect_failed'));
            $this->conn->registerHandler('Reconnect', array($this, 'handle_reconnect'));
            $this->conn->signon();
            $this->lastPing = time();
        }
        return $this->conn;
    }

    /**
    * Called by the idle process to send a ping
    * when necessary
    *
    * @return void
    */
    protected function send_ping() {
        $this->connect();
        if (!$this->conn) {
            return false;
        }

        $this->conn->sendPing();
        $this->lastPing = time();
        $this->pingInterval = 50;
        return true;
    }

    /**
     * Update the time till the next ping
     *
     * @param $data Time till next ping
     * @return void
     */
    public function update_ping_time($data) {
        $this->pingInterval = $data;
    }

    /**
    * Called via a callback when a message is received
    *
    * Passes it back to the queuing system
    *
    * @param array $data Data
    * @return boolean
    */
    public function handle_msn_message($data) {
        $this->plugin->enqueueIncomingRaw($data);
        return true;
    }

    /**
    * Called via a callback when a session becomes ready
    *
    * @param array $data Data
    */
    public function handle_session_ready($data) {
        $sessionFailed = false;
        $wm = Msn_waiting_message::top($data['to']);
        while ($wm != NULL) {
            if ($sessionFailed) {
                $this->plugin->sendMessage($wm->screenname, $wm->message);
                $sessionFailed = true;
            } elseif (!$this->conn->sendMessage($wm->screenname, $wm->message, $ignore)) {
                $this->plugin->sendMessage($wm->screenname, $wm->message);
            }

            $wm->delete();
            $wm = Msn_waiting_message::top($data['to']);
        }
    }

    /**
    * Requeue messages from the waiting table so we try
    * to send them again
    *
    * @return void
    */
    protected function requeue_waiting_messages() {
        $wm = Msn_waiting_message::top();
        while ($wm != NULL) {
            $this->plugin->sendMessage($wm->screenname, $wm->message);
            $wm->delete();
            $wm = Msn_waiting_message::top();
        }
    }

    /**
    * Called by callback to log failure during connect
    *
    * @param string $message error message reported
    * @return void
    */
    public function handle_connect_failed($message) {
        common_log(LOG_NOTICE, 'MSN connect failed, retrying: ' . $message);
    }

    /**
    * Called by callback to log reconnection
    *
    * @param void $data Not used (there to keep callback happy)
    * @return void
    */
    public function handle_reconnect($data) {
        common_log(LOG_NOTICE, 'MSN reconnecting');
        // Requeue messages waiting in the DB
        $this->requeue_waiting_messages();
    }

    /**
    * Enters a message into the database for sending via a callback
    * when the session is established
    *
    * @param string $to Intended recipient
    * @param string $message Message
    */
    protected function enqueue_waiting_message($to, $message) {
        $wm = new Msn_waiting_message();

        $wm->screenname = $to;
        $wm->message    = $message;
        $wm->created    = common_sql_now();
        $result         = $wm->insert();

        if (!$result) {
            common_log_db_error($wm, 'INSERT', __FILE__);
            // TRANS: Server exception thrown when a message to be sent through MSN cannot be added to the database queue.
            throw new ServerException(_m('Database error inserting queue item.'));
        }

        return true;
    }

    /**
     * Send a message using the daemon
     *
     * @param $data Message data
     * @return boolean true on success
     */
    public function send_raw_message($data) {
        $this->connect();
        if (!$this->conn) {
            return false;
        }

        $waitForSession = false;
        if (!$this->conn->sendMessage($data['to'], $data['message'], $waitForSession)) {
            if ($waitForSession) {
                $this->enqueue_waiting_message($data['to'], $data['message']);
            } else {
                return false;
            }
        }

        // Sending a command updates the time till next ping
        $this->lastPing = time();
        $this->pingInterval = 50;
        return true;
    }
}

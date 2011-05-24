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
 * IRC background connection manager for IRC-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has IRC enabled.
 */
class IrcManager extends ImManager {
    protected $conn = null;
    protected $lastPing = null;
    protected $messageWaiting = true;
    protected $lastMessage = null;

    protected $regChecks = array();
    protected $regChecksLookup = array();

    protected $connected = false;

    /**
     * Initialize connection to server.
     *
     * @return boolean true on success
     */
    public function start($master) {
        if (parent::start($master)) {
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
     * Request a maximum timeout for listeners before the next idle period.
     *
     * @return integer Maximum timeout
     */
    public function timeout() {
        if ($this->messageWaiting) {
            return 1;
        } else {
            return $this->plugin->pinginterval;
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     *
     * @return void
     */
    public function idle() {
        // Send a ping if necessary
        if (empty($this->lastPing) || time() - $this->lastPing > $this->plugin->pinginterval) {
            $this->sendPing();
        }

        if ($this->connected) {
            // Send a waiting message if appropriate
            if ($this->messageWaiting && time() - $this->lastMessage > 1) {
                $wm = Irc_waiting_message::top();
                if ($wm === NULL) {
                    $this->messageWaiting = false;
                    return;
                }

                $data = unserialize($wm->data);
                $wm->incAttempts();

                if ($this->send_raw_message($data)) {
                    $wm->delete();
                } else {
                    if ($wm->attempts <= common_config('queue', 'max_retries')) {
                        // Try again next idle
                        $wm->releaseClaim();
                    } else {
                        // Exceeded the maximum number of retries
                        $wm->delete();
                    }
                }
            }
        }
    }

    /**
     * Process IRC events that have come in over the wire.
     *
     * @param resource $socket Socket to handle input on
     * @return void
     */
    public function handleInput($socket) {
        common_log(LOG_DEBUG, 'Servicing the IRC queue.');
        $this->stats('irc_process');

        try {
            $this->conn->handleEvents();
        } catch (Phergie_Driver_Exception $e) {
            $this->connected = false;
            $this->conn->reconnect();
        }
    }

    /**
    * Initiate connection
    *
    * @return void
    */
    public function connect() {
        if (!$this->conn) {
            $this->conn = new Phergie_StatusnetBot;

            $config = new Phergie_Config;
            $config->readArray(
                array(
                    'connections' => array(
                        array(
                            'host' => $this->plugin->host,
                            'port' => $this->plugin->port,
                            'username' => $this->plugin->username,
                            'realname' => $this->plugin->realname,
                            'nick' => $this->plugin->nick,
                            'password' => $this->plugin->password,
                            'transport' => $this->plugin->transporttype,
                            'encoding' => $this->plugin->encoding
                        )
                    ),

                    'driver' => 'statusnet',

                    'processor' => 'async',
                    'processor.options' => array('sec' => 0, 'usec' => 0),

                    'plugins' => array(
                        'Pong',
                        'NickServ',
                        'AutoJoin',
                        'Statusnet',
                    ),

                    'plugins.autoload' => true,

                    // Uncomment to enable debugging output
                    //'ui.enabled' => true,

                    'nickserv.password' => $this->plugin->nickservpassword,
                    'nickserv.identify_message' => $this->plugin->nickservidentifyregexp,

                    'autojoin.channels' => $this->plugin->channels,

                    'statusnet.messagecallback' => array($this, 'handle_irc_message'),
                    'statusnet.regcallback' => array($this, 'handle_reg_response'),
                    'statusnet.connectedcallback' => array($this, 'handle_connected'),
                    'statusnet.unregregexp' => $this->plugin->unregregexp,
                    'statusnet.regregexp' => $this->plugin->regregexp
                )
            );

            $this->conn->setConfig($config);
            $this->conn->connect();
            $this->lastPing = time();
            $this->lastMessage = time();
        }
        return $this->conn;
    }

    /**
    * Called via a callback when a message is received
    * Passes it back to the queuing system
    *
    * @param array $data Data
    * @return boolean
    */
    public function handle_irc_message($data) {
        $this->plugin->enqueueIncomingRaw($data);
        return true;
    }

    /**
    * Called via a callback when NickServ responds to
    * the bots query asking if a nick is registered
    *
    * @param array $data Data
    * @return void
    */
    public function handle_reg_response($data) {
        // Retrieve data
        $screenname = $data['screenname'];
        $nickdata = $this->regChecks[$screenname];
        $usernick = $nickdata['user']->nickname;

        if (isset($this->regChecksLookup[$usernick])) {
            if ($data['registered']) {
                // Send message
                $this->plugin->sendConfirmationCode($screenname, $nickdata['code'], $nickdata['user'], true);
            } else {
                // TRANS: Message given when using an unregistered IRC nickname.
                $this->plugin->sendMessage($screenname, _m('Your nickname is not registered so IRC connectivity cannot be enabled.'));

                $confirm = new Confirm_address();

                $confirm->user_id      = $user->id;
                $confirm->address_type = $this->plugin->transport;

                if ($confirm->find(true)) {
                    $result = $confirm->delete();

                    if (!$result) {
                        common_log_db_error($confirm, 'DELETE', __FILE__);
                        // TRANS: Server error thrown on database error when deleting IRC nickname confirmation.
                        $this->serverError(_m('Could not delete confirmation.'));
                        return;
                    }
                }
            }

            // Unset lookup value
            unset($this->regChecksLookup[$usernick]);

            // Unset data
            unset($this->regChecks[$screename]);
        }
    }

    /**
    * Called when the connection is established
    *
    * @return void
    */
    public function handle_connected() {
        $this->connected = true;
    }

    /**
    * Enters a message into the database for sending when ready
    *
    * @param string $command Command
    * @param array $args Arguments
    * @return boolean
    */
    protected function enqueue_waiting_message($data) {
        $wm = new Irc_waiting_message();

        $wm->data       = serialize($data);
        $wm->prioritise = $data['prioritise'];
        $wm->attempts   = 0;
        $wm->created    = common_sql_now();
        $result         = $wm->insert();

        if (!$result) {
            common_log_db_error($wm, 'INSERT', __FILE__);
            // TRANS: Server exception thrown when an IRC waiting queue item could not be added to the database.
            throw new ServerException(_m('Database error inserting IRC waiting queue item.'));
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

        if ($data['type'] != 'delayedmessage') {
            if ($data['type'] != 'message') {
                // Nick checking
                $nickdata = $data['nickdata'];
                $usernick = $nickdata['user']->nickname;
                $screenname = $nickdata['screenname'];

                // Cancel any existing checks for this user
                if (isset($this->regChecksLookup[$usernick])) {
                    unset($this->regChecks[$this->regChecksLookup[$usernick]]);
                }

                $this->regChecks[$screenname] = $nickdata;
                $this->regChecksLookup[$usernick] = $screenname;
            }

            // If there is a backlog or we need to wait, queue the message
            if ($this->messageWaiting || time() - $this->lastMessage < 1) {
                $this->enqueue_waiting_message(
                    array(
                        'type' => 'delayedmessage',
                        'prioritise' => $data['prioritise'],
                        'data' => $data['data']
                    )
                );
                $this->messageWaiting = true;
                return true;
            }
        }

        try {
            $this->conn->send($data['data']['command'], $data['data']['args']);
        } catch (Phergie_Driver_Exception $e) {
            $this->connected = false;
            $this->conn->reconnect();
            return false;
        }

        $this->lastMessage = time();
        return true;
    }

    /**
    * Sends a ping
    *
    * @return void
    */
    protected function sendPing() {
        $this->lastPing = time();
        $this->conn->send('PING', $this->lastPing);
    }
}

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
 * XMPP background connection manager for XMPP-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has XMPP enabled.
 */
class XmppManager extends ImManager
{
    protected $lastping = null;
    protected $pingid = null;

    public $conn = null;

    const PING_INTERVAL = 120;

    /**
     * Initialize connection to server.
     * @return boolean true on success
     */
    public function start($master)
    {
        if(parent::start($master))
        {
            $this->connect();
            return true;
        }else{
            return false;
        }
    }

    function send_raw_message($data)
    {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        $this->conn->send($data);
        return true;
    }

    /**
     * Message pump is triggered on socket input, so we only need an idle()
     * call often enough to trigger our outgoing pings.
     */
    function timeout()
    {
        return self::PING_INTERVAL;
    }

    /**
     * Process XMPP events that have come in over the wire.
     * @fixme may kill process on XMPP error
     * @param resource $socket
     */
    public function handleInput($socket)
    {
        // Process the queue for as long as needed
        try {
            common_log(LOG_DEBUG, "Servicing the XMPP queue.");
            $this->stats('xmpp_process');
            $this->conn->processTime(0);
        } catch (XMPPHP_Exception $e) {
            common_log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
            die($e->getMessage());
        }
    }

    /**
     * Lists the IM connection socket to allow i/o master to wake
     * when input comes in here as well as from the queue source.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        $this->connect();
        if($this->conn){
            return array($this->conn->getSocket());
        }else{
            return array();
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     * Send keepalive pings to server.
     *
     * Side effect: kills process on exception from XMPP library.
     *
     * @todo FIXME: non-dying error handling
     */
    public function idle($timeout=0)
    {
        $now = time();
        if (empty($this->lastping) || $now - $this->lastping > self::PING_INTERVAL) {
            try {
                $this->send_ping();
            } catch (XMPPHP_Exception $e) {
                common_log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
                die($e->getMessage());
            }
        }
    }

    function connect()
    {
        if (!$this->conn || $this->conn->isDisconnected()) {
            $resource = 'queue' . posix_getpid();
            $this->conn = new Sharing_XMPP($this->plugin->host ?
                                    $this->plugin->host :
                                    $this->plugin->server,
                                    $this->plugin->port,
                                    $this->plugin->user,
                                    $this->plugin->password,
                                    $this->plugin->resource,
                                    $this->plugin->server,
                                    $this->plugin->debug ?
                                    true : false,
                                    $this->plugin->debug ?
                                    XMPPHP_Log::LEVEL_VERBOSE :  null
                                    );

            if (!$this->conn) {
                return false;
            }
            $this->conn->addEventHandler('message', 'handle_xmpp_message', $this);
            $this->conn->addEventHandler('reconnect', 'handle_xmpp_reconnect', $this);
            $this->conn->setReconnectTimeout(600);

            $this->conn->autoSubscribe();
            $this->conn->useEncryption($this->plugin->encryption);

            try {
                $this->conn->connect(true); // true = persistent connection
            } catch (XMPPHP_Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                return false;
            }

            $this->conn->processUntil('session_start');
            // TRANS: Presence announcement for XMPP.
            $this->send_presence(_m('Send me a message to post a notice'), 'available', null, 'available', 100);
        }
        return $this->conn;
    }

    function send_ping()
    {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        $now = time();
        if (!isset($this->pingid)) {
            $this->pingid = 0;
        } else {
            $this->pingid++;
        }

        common_log(LOG_DEBUG, "Sending ping #{$this->pingid}");
		$this->conn->send("<iq from='{" . $this->plugin->daemonScreenname() . "}' to='{$this->plugin->server}' id='ping_{$this->pingid}' type='get'><ping xmlns='urn:xmpp:ping'/></iq>");
        $this->lastping = $now;
        return true;
    }

    function handle_xmpp_message(&$pl)
    {
        $this->plugin->enqueueIncomingRaw($pl);
        return true;
    }

    /**
     * Callback for Jabber reconnect event
     * @param $pl
     */
    function handle_xmpp_reconnect(&$pl)
    {
        common_log(LOG_NOTICE, 'XMPP reconnected');

        $this->conn->processUntil('session_start');
        // TRANS: Message for XMPP reconnect.
        $this->send_presence(_m('Send me a message to post a notice'), 'available', null, 'available', 100);
    }

    /**
     * sends a presence stanza on the XMPP network
     *
     * @param string $status   current status, free-form string
     * @param string $show     structured status value
     * @param string $to       recipient of presence, null for general
     * @param string $type     type of status message, related to $show
     * @param int    $priority priority of the presence
     *
     * @return boolean success value
     */

    function send_presence($status, $show='available', $to=null,
                                  $type = 'available', $priority=null)
    {
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }
        $this->conn->presence($status, $show, $to, $type, $priority);
        return true;
    }

    /**
     * sends a "special" presence stanza on the XMPP network
     *
     * @param string $type   Type of presence
     * @param string $to     JID to send presence to
     * @param string $show   show value for presence
     * @param string $status status value for presence
     *
     * @return boolean success flag
     *
     * @see send_presence()
     */

    function special_presence($type, $to=null, $show=null, $status=null)
    {
        // @todo FIXME: why use this instead of send_presence()?
        $this->connect();
        if (!$this->conn || $this->conn->isDisconnected()) {
            return false;
        }

        $to     = htmlspecialchars($to);
        $status = htmlspecialchars($status);

        $out = "<presence";
        if ($to) {
            $out .= " to='$to'";
        }
        if ($type) {
            $out .= " type='$type'";
        }
        if ($show == 'available' and !$status) {
            $out .= "/>";
        } else {
            $out .= ">";
            if ($show && ($show != 'available')) {
                $out .= "<show>$show</show>";
            }
            if ($status) {
                $out .= "<status>$status</status>";
            }
            $out .= "</presence>";
        }
        $this->conn->send($out);
        return true;
    }
}

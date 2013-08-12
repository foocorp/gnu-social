<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Send and receive notices using an IRC network
 *
 * PHP version 5
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
 *
 * @category  IM
 * @package   StatusNet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

// We bundle the Phergie library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/phergie');

/**
 * Plugin for IRC
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class IrcPlugin extends ImPlugin {
    public $host =  null;
    public $port = null;
    public $username = null;
    public $realname = null;
    public $nick = null;
    public $password = null;
    public $nickservidentifyregexp = null;
    public $nickservpassword = null;
    public $channels = null;
    public $transporttype = null;
    public $encoding = null;
    public $pinginterval = null;

    public $regcheck = null;
    public $unregregexp = null;
    public $regregexp = null;

    public $transport = 'irc';
    protected $whiteList;
    protected $fake_irc;

    /**
     * Get the internationalized/translated display name of this IM service
     *
     * @return string Name of service
     */
    public function getDisplayName() {
        // TRANS: Service name for IRC.
        return _m('IRC');
    }

    /**
     * Normalize a screenname for comparison
     *
     * @param string $screenname Screenname to normalize
     * @return string An equivalent screenname in normalized form
     */
    public function normalize($screenname) {
        $screenname = str_replace(" ","", $screenname);
        return strtolower($screenname);
    }

    /**
     * Get the screenname of the daemon that sends and receives messages
     *
     * @return string Screenname
     */
    public function daemonScreenname() {
        return $this->nick;
    }

    /**
     * Validate (ensure the validity of) a screenname
     *
     * @param string $screenname Screenname to validate
     * @return boolean true if screenname is valid
     */
    public function validate($screenname) {
        if (preg_match('/\A[a-z0-9\-_]{1,1000}\z/i', $screenname)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onAutoload($cls) {
        $dir = dirname(__FILE__);

        switch ($cls) {
            case 'IrcManager':
                include_once $dir . '/'.strtolower($cls).'.php';
                return false;
            case 'Fake_Irc':
            case 'Irc_waiting_message':
            case 'ChannelResponseChannel':
                include_once $dir . '/'. $cls .'.php';
                return false;
            default:
                if (substr($cls, 0, 7) == 'Phergie') {
                    include_once str_replace('_', DIRECTORY_SEPARATOR, $cls) . '.php';
                    return false;
                }
                return true;
        }
    }

    /*
     * Start manager on daemon start
     *
     * @param array &$versions Array to insert manager into
     * @return boolean
     */
    public function onStartImDaemonIoManagers(&$classes) {
        parent::onStartImDaemonIoManagers($classes);
        $classes[] = new IrcManager($this); // handles sending/receiving
        return true;
    }

    /**
    * Ensure the database table is present
    *
    */
    public function onCheckSchema() {
        $schema = Schema::get();

        // For storing messages while sessions become ready
        $schema->ensureTable('irc_waiting_message',
                             array(new ColumnDef('id', 'integer', null,
                                                 false, 'PRI', null, null, true),
                                   new ColumnDef('data', 'blob', null, false),
                                   new ColumnDef('prioritise', 'tinyint', 1, false),
                                   new ColumnDef('attempts', 'integer', null, false),
                                   new ColumnDef('created', 'datetime', null, false),
                                   new ColumnDef('claimed', 'datetime')));

        return true;
    }

    /**
    * Get a microid URI for the given screenname
    *
    * @param string $screenname Screenname
    * @return string microid URI
    */
    public function microiduri($screenname) {
        return 'irc:' . $screenname;
    }

    /**
     * Send a message to a given screenname
     *
     * @param string $screenname Screenname to send to
     * @param string $body Text to send
     * @return boolean true on success
     */
    public function sendMessage($screenname, $body) {
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $this->fake_irc->doPrivmsg($screenname, $line);
            $this->enqueueOutgoingRaw(array('type' => 'message', 'prioritise' => 0, 'data' => $this->fake_irc->would_be_sent));
        }
        return true;
    }

    /**
     * Accept a queued input message.
     *
     * @return boolean true if processing completed, false if message should be reprocessed
     */
    public function receiveRawMessage($data) {
        if (strpos($data['source'], '#') === 0) {
            $message = $data['message'];
            $parts = explode(' ', $message, 2);
            $command = $parts[0];
            if (in_array($command, $this->whiteList)) {
                $this->handle_channel_incoming($data['sender'], $data['source'], $message);
            } else {
                $this->handleIncoming($data['sender'], $message);
            }
        } else {
            $this->handleIncoming($data['sender'], $data['message']);
        }
        return true;
    }

    /**
     * Helper for handling incoming messages from a channel requiring response
     * to the channel instead of via PM
     *
     * @param string $nick Screenname the message was sent from
     * @param string $channel Channel the message originated from
     * @param string $message Message text
     * @param boolean true on success
     */
    protected function handle_channel_incoming($nick, $channel, $notice_text) {
        $user = $this->getUser($nick);
        // For common_current_user to work
        global $_cur;
        $_cur = $user;

        if (!$user) {
            $this->sendFromSite($nick, 'Unknown user; go to ' .
                             common_local_url('imsettings') .
                             ' to add your address to your account');
            common_log(LOG_WARNING, 'Message from unknown user ' . $nick);
            return;
        }
        if ($this->handle_channel_command($user, $channel, $notice_text)) {
            common_log(LOG_INFO, "Command message by $nick handled.");
            return;
        } else if ($this->isAutoreply($notice_text)) {
            common_log(LOG_INFO, 'Ignoring auto reply from ' . $nick);
            return;
        } else if ($this->isOtr($notice_text)) {
            common_log(LOG_INFO, 'Ignoring OTR from ' . $nick);
            return;
        } else {
            common_log(LOG_INFO, 'Posting a notice from ' . $user->nickname);
            $this->addNotice($nick, $user, $notice_text);
        }

        $user->free();
        unset($user);
        unset($_cur);
        unset($message);
    }

    /**
     * Attempt to handle a message from a channel as a command
     *
     * @param User $user User the message is from
     * @param string $channel Channel the message originated from
     * @param string $body Message text
     * @return boolean true if the message was a command and was executed, false if it was not a command
     */
    protected function handle_channel_command($user, $channel, $body) {
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $body);
        if ($cmd) {
            $chan = new ChannelResponseChannel($this, $channel);
            $cmd->execute($chan);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send a confirmation code to a user
     *
     * @param string $screenname screenname sending to
     * @param string $code the confirmation code
     * @param User $user user sending to
     * @return boolean success value
     */
    public function sendConfirmationCode($screenname, $code, $user, $checked = false) {
        // TRANS: Body text for e-mail confirmation message for IRC.
        // TRANS: %1$s is a user nickname, %2$s is the StatusNet sitename,
        // TRANS: %3$s is the plugin display name ("IRC"), %4$s is the confirm address URL.
        $body = sprintf(_m('User "%1$s" on %2$s has said that your %3$s screenname belongs to them. ' .
          'If that\'s true, you can confirm by clicking on this URL: ' .
          '%4$s' .
          ' . (If you cannot click it, copy-and-paste it into the ' .
          'address bar of your browser). If that user is not you, ' .
          'or if you did not request this confirmation, just ignore this message.'),
          $user->nickname, common_config('site', 'name'), $this->getDisplayName(), common_local_url('confirmaddress', array('code' => $code)));

        if ($this->regcheck && !$checked) {
            return $this->checked_sendConfirmationCode($screenname, $code, $user);
        } else {
            return $this->sendMessage($screenname, $body);
        }
    }

    /**
    * Only sends the confirmation message if the nick is
    * registered
    *
    * @param string $screenname Screenname sending to
    * @param string $code The confirmation code
    * @param User $user User sending to
    * @return boolean true on succes
    */
    public function checked_sendConfirmationCode($screenname, $code, $user) {
        $this->fake_irc->doPrivmsg('NickServ', 'INFO '.$screenname);
        $this->enqueueOutgoingRaw(
            array(
                'type' => 'nickcheck',
                'prioritise' => 1,
                'data' => $this->fake_irc->would_be_sent,
                'nickdata' =>
                    array(
                        'screenname' => $screenname,
                        'code' => $code,
                        'user' => $user
                    )
            )
        );
        return true;
    }

    /**
    * Initialize plugin
    *
    * @return boolean
    */
    public function initialize() {
        if (!isset($this->host)) {
            // TRANS: Exception thrown when initialising the IRC plugin fails because of an incorrect configuration.
            throw new Exception(_m('You must specify a host.'));
        }
        if (!isset($this->username)) {
            // TRANS: Exception thrown when initialising the IRC plugin fails because of an incorrect configuration.
            throw new Exception(_m('You must specify a username.'));
        }
        if (!isset($this->realname)) {
            // TRANS: Exception thrown when initialising the IRC plugin fails because of an incorrect configuration.
            throw new Exception(_m('You must specify a "real name".'));
        }
        if (!isset($this->nick)) {
            // TRANS: Exception thrown when initialising the IRC plugin fails because of an incorrect configuration.
            throw new Exception(_m('You must specify a nickname.'));
        }

        if (!isset($this->port)) {
            $this->port = 6667;
        }
        if (!isset($this->transporttype)) {
            $this->transporttype = 'tcp';
        }
        if (!isset($this->encoding)) {
            $this->encoding = 'UTF-8';
        }
        if (!isset($this->pinginterval)) {
            $this->pinginterval = 120;
        }

        if (!isset($this->regcheck)) {
            $this->regcheck = true;
        }

        $this->fake_irc = new Fake_Irc;

        /*
         * Commands allowed to return output to a channel
         */
        $this->whiteList = array('stats', 'last', 'get');

        return true;
    }

    /**
     * Get plugin information
     *
     * @param array $versions Array to insert information into
     * @return void
     */
    public function onPluginVersion(&$versions) {
        $versions[] = array('name' => 'IRC',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Luke Fitzgerald',
                            'homepage' => 'http://status.net/wiki/Plugin:IRC',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The IRC plugin allows users to send and receive notices over an IRC network.'));
        return true;
    }
}

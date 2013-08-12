<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to do "real time" updates using Meteor
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/Realtime/RealtimePlugin.php';

/**
 * Plugin to do realtime updates using Meteor
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class MeteorPlugin extends RealtimePlugin
{
    public $webserver     = null;
    public $webport       = null;
    public $controlport   = null;
    public $controlserver = null;
    public $channelbase   = null;
    public $protocol      = null;
    public $persistent    = true;
    protected $_socket    = null;

    function __construct($webserver=null, $webport=4670, $controlport=4671, $controlserver=null, $channelbase='', $protocol='http')
    {
        global $config;

        $this->webserver     = (empty($webserver)) ? $config['site']['server'] : $webserver;
        $this->webport       = $webport;
        $this->controlport   = $controlport;
        $this->controlserver = (empty($controlserver)) ? $webserver : $controlserver;
        $this->channelbase   = $channelbase;
		$this->protocol      = $protocol;
		
        parent::__construct();
    }

    /**
     * Pull settings from config file/database if set.
     */
    function initialize()
    {
        $settings = array('webserver',
                          'webport',
                          'controlport',
                          'controlserver',
                          'channelbase',
                          'protocol');
        foreach ($settings as $name) {
            $val = common_config('meteor', $name);
            if ($val !== false) {
                $this->$name = $val;
            }
        }

        return parent::initialize();
    }

    function _getScripts()
    {
        $scripts = parent::_getScripts();
        if ($this->protocol == 'https') {
        	$scripts[] = 'https://'.$this->webserver.(($this->webport == 443) ? '':':'.$this->webport).'/meteor.js';
        } else {
        	$scripts[] = 'http://'.$this->webserver.(($this->webport == 80) ? '':':'.$this->webport).'/meteor.js';
        }
        $scripts[] = $this->path('meteorupdater.min.js');
        return $scripts;
    }

    function _updateInitialize($timeline, $user_id)
    {
        $script = parent::_updateInitialize($timeline, $user_id);
        $ours = sprintf("MeteorUpdater.init(%s, %s, %s);",
        				json_encode($this->webserver),
        				json_encode($this->webport),
        				json_encode($timeline));
        return $script." ".$ours;
    }

    function _connect()
    {
        $controlserver = (empty($this->controlserver)) ? $this->webserver : $this->controlserver;

        $errno = $errstr = null;
        $timeout = 5;
        $flags = STREAM_CLIENT_CONNECT;
        if ($this->persistent) $flags |= STREAM_CLIENT_PERSISTENT;

        // May throw an exception.
        $this->_socket = stream_socket_client("tcp://{$controlserver}:{$this->controlport}", $errno, $errstr, $timeout, $flags);
        if (!$this->_socket) {
            // TRANS: Exception. %1$s is the control server, %2$s is the control port.
            throw new Exception(sprintf(_m('Could not connect to %1$s on %2$s.'),$controlserver,$this->controlport));
        }
    }

    function _publish($channel, $message)
    {
        $message = json_encode($message);
        $message = addslashes($message);
        $cmd = "ADDMESSAGE $channel $message\n";
        $cnt = fwrite($this->_socket, $cmd);
        $result = fgets($this->_socket);
        if (preg_match('/^ERR (.*)$/', $result, $matches)) {
            // TRANS: Exception. %s is the Meteor message that could not be added.
            throw new Exception(sprintf(_m('Error adding meteor message "%s".'),$matches[1]));
        }
        // TODO: parse and deal with result
    }

    function _disconnect()
    {
        if (!$this->persistent) {
            $cnt = fwrite($this->_socket, "QUIT\n");
            @fclose($this->_socket);
        }
    }

    // Meteord flips out with default '/' separator

    function _pathToChannel($path)
    {
        if (!empty($this->channelbase)) {
            array_unshift($path, $this->channelbase);
        }
        return implode('-', $path);
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Meteor',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Meteor',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to do "real time" updates using Meteor.'));
        return true;
    }
}

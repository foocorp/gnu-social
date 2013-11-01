<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to do "real time" updates using Orbited + STOMP
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
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/Realtime/RealtimePlugin.php';

/**
 * Plugin to do realtime updates using Orbited + STOMP
 *
 * This plugin pushes data to a STOMP server which is then served to the
 * browser by the Orbited server.
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */
class OrbitedPlugin extends RealtimePlugin
{
    public $webserver   = null;
    public $webport     = null;
    public $channelbase = null;
    public $stompserver = null;
    public $stompport   = null;
    public $username    = null;
    public $password    = null;
    public $webuser     = null;
    public $webpass     = null;

    protected $con      = null;

    function onStartShowHeadElements($action)
    {
        // See http://orbited.org/wiki/Deployment#Cross-SubdomainDeployment
        $action->element('script', null, ' document.domain = document.domain; ');
    }

    function _getScripts()
    {
        $scripts = parent::_getScripts();

        $port = (is_null($this->webport)) ? 8000 : $this->webport;

        $server = (is_null($this->webserver)) ? common_config('site', 'server') : $this->webserver;

        $root = 'http://'.$server.(($port == 80) ? '':':'.$port);

        $scripts[] = $root.'/static/Orbited.js';
        $scripts[] = 'plugins/Orbited/orbitedextra.js';
        $scripts[] = $root.'/static/protocols/stomp/stomp.js';
        $scripts[] = 'plugins/Orbited/orbitedupdater.js';

        return $scripts;
    }

    function _updateInitialize($timeline, $user_id)
    {
        $script = parent::_updateInitialize($timeline, $user_id);

        $server = $this->_getStompServer();
        $port   = $this->_getStompPort();

        return $script." OrbitedUpdater.init(\"$server\", $port, ".
          "\"{$timeline}\", \"{$this->webuser}\", \"{$this->webpass}\");";
    }

    function _connect()
    {
        require_once(INSTALLDIR.'/extlib/Stomp.php');

        $url = $this->_getStompUrl();

        $this->con = new Stomp($url);

        if ($this->con->connect($this->username, $this->password)) {
            $this->log(LOG_INFO, "Connected.");
        } else {
            $this->log(LOG_ERR, 'Failed to connect to queue server');
            // TRANS: Server exception thrown when no connection can be made to a queue server.
            throw new ServerException(_m('Failed to connect to queue server.'));
        }
    }

    function _publish($channel, $message)
    {
        $result = $this->con->send($channel,
                                   json_encode($message));

        return $result;
        // @todo Parse and deal with result.
    }

    function _disconnect()
    {
        $this->con->disconnect();
    }

    function _pathToChannel($path)
    {
        if (!empty($this->channelbase)) {
            array_unshift($path, $this->channelbase);
        }
        return '/' . implode('/', $path);
    }

    function _getStompServer()
    {
        return (!is_null($this->stompserver)) ? $this->stompserver :
        (!is_null($this->webserver)) ? $this->webserver :
        common_config('site', 'server');
    }

    function _getStompPort()
    {
        return (!is_null($this->stompport)) ? $this->stompport : 61613;
    }

    function _getStompUrl()
    {
        $server = $this->_getStompServer();
        $port   = $this->_getStompPort();
        return "tcp://$server:$port/";
    }

    /**
     * Add our version information to output
     *
     * @param array &$versions Array of version-data arrays
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Orbited',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Orbited',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to make updates using Orbited and STOMP.'));
        return true;
    }
}

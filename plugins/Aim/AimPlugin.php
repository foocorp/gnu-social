<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Send and receive notices using the AIM network
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}
// We bundle the phptoclib library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/phptoclib');

/**
 * Plugin for AIM
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AimPlugin extends ImPlugin
{
    public $user =  null;
    public $password = null;
    public $publicFeed = array();

    public $transport = 'aim';

    function getDisplayName()
    {
        // TRANS: Display name.
        return _m('AIM');
    }

    function normalize($screenname)
    {
		$screenname = str_replace(" ","", $screenname);
        return strtolower($screenname);
    }

    function daemonScreenname()
    {
        return $this->user;
    }

    function validate($screenname)
    {
        if(preg_match('/^[a-z]\w{2,15}$/i', $screenname)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Aim':
            require_once(INSTALLDIR.'/plugins/Aim/extlib/phptoclib/aimclassw.php');
            return false;
        case 'AimManager':
            include_once $dir . '/'.strtolower($cls).'.php';
            return false;
        case 'Fake_Aim':
            include_once $dir . '/'. $cls .'.php';
            return false;
        default:
            return true;
        }
    }

    function onStartImDaemonIoManagers(&$classes)
    {
        parent::onStartImDaemonIoManagers($classes);
        $classes[] = new AimManager($this); // handles sending/receiving
        return true;
    }

    function microiduri($screenname)
    {
        return 'aim:' . $screenname;
    }

    function sendMessage($screenname, $body)
    {
        $this->fake_aim->sendIm($screenname, $body);
	    $this->enqueueOutgoingRaw($this->fake_aim->would_be_sent);
        return true;
    }

    /**
     * Accept a queued input message.
     *
     * @return true if processing completed, false if message should be reprocessed
     */
    function receiveRawMessage($message)
    {
        $info=Aim::getMessageInfo($message);
        $from = $info['from'];
        $user = $this->getUser($from);
        $notice_text = $info['message'];

        $this->handleIncoming($from, $notice_text);

        return true;
    }

    function initialize(){
        if(!isset($this->user)){
            // TRANS: Exception thrown in AIM plugin when user has not been specified.
            throw new Exception(_m('Must specify a user.'));
        }
        if(!isset($this->password)){
            // TRANS: Exception thrown in AIM plugin when password has not been specified.
            throw new Exception(_m('Must specify a password.'));
        }

        $this->fake_aim = new Fake_Aim($this->user,$this->password,4);
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'AIM',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:AIM',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The AIM plugin allows users to send and receive notices over the AIM network.'));
        return true;
    }
}

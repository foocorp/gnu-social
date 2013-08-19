<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Send and receive notices using the MSN network
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
// We bundle the phpmsnclass library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/phpmsnclass');

/**
 * Plugin for MSN
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class MsnPlugin extends ImPlugin {
    public $user = null;
    public $password = null;
    public $nickname = null;
    public $transport = 'msn';

    /**
     * Get the internationalized/translated display name of this IM service
     *
     * @return string Name of service
     */
    public function getDisplayName() {
        // TRANS: Display name of the MSN instant messaging service.
        return _m('MSN');
    }

    /**
     * Normalize a screenname for comparison
     *
     * @param string $screenname screenname to normalize
     * @return string an equivalent screenname in normalized form
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
        return $this->user;
    }

    /**
     * Validate (ensure the validity of) a screenname
     *
     * @param string $screenname screenname to validate
     * @return boolean
     */
    public function validate($screenname) {
        return Validate::email($screenname, common_config('email', 'check_domain'));
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
            case 'MSN':
                require_once(INSTALLDIR.'/plugins/Msn/extlib/phpmsnclass/msn.class.php');
                return false;
            case 'MsnManager':
            case 'Msn_waiting_message':
                include_once $dir . '/'.strtolower($cls).'.php';
                return false;
            default:
                return true;
        }
    }

    /*
     * Start manager on daemon start
     *
     * @return boolean
     */
    public function onStartImDaemonIoManagers(&$classes) {
        parent::onStartImDaemonIoManagers($classes);
        $classes[] = new MsnManager($this); // handles sending/receiving
        return true;
    }

    /**
    * Ensure the database table is present
    *
    */
    public function onCheckSchema() {
        $schema = Schema::get();

        // For storing messages while sessions become ready
        $schema->ensureTable('msn_waiting_message', Msn_waiting_message::schemaGet());
        return true;
    }

    /**
    * Get a microid URI for the given screenname
    *
    * @param string $screenname
    * @return string microid URI
    */
    public function microiduri($screenname) {
        return 'msnim:' . $screenname;
    }

    /**
     * Send a message to a given screenname
     *
     * @param string $screenname Screenname to send to
     * @param string $body Text to send
     * @return boolean success value
     */
    public function sendMessage($screenname, $body) {
        $this->enqueueOutgoingRaw(array('to' => $screenname, 'message' => $body));
        return true;
    }

    /**
     * Accept a queued input message.
     *
     * @param array $data Data
     * @return true if processing completed, false if message should be reprocessed
     */
    public function receiveRawMessage($data) {
        $this->handleIncoming($data['sender'], $data['message']);
        return true;
    }

    /**
    * Initialize plugin
    *
    * @return boolean
    */
    public function initialize() {
        if (!isset($this->user)) {
            // TRANS: Exception thrown when configuration for the MSN plugin is incomplete.
            throw new Exception(_m('Must specify a user.'));
        }
        if (!isset($this->password)) {
            // TRANS: Exception thrown when configuration for the MSN plugin is incomplete.
            throw new Exception(_m('Must specify a password.'));
        }
        if (!isset($this->nickname)) {
            // TRANS: Exception thrown when configuration for the MSN plugin is incomplete.
            throw new Exception(_m('Must specify a nickname.'));
        }

        return true;
    }

    /**
     * Get plugin information
     *
     * @param array $versions array to insert information into
     * @return void
     */
    public function onPluginVersion(&$versions) {
        $versions[] = array(
            'name' => 'MSN',
            'version' => STATUSNET_VERSION,
            'author' => 'Luke Fitzgerald',
            'homepage' => 'http://status.net/wiki/Plugin:MSN',
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('The MSN plugin allows users to send and receive notices over the MSN network.')
        );
        return true;
    }
}

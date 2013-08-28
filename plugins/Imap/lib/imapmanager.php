<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * IMAP IO Manager
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @maintainer Craig Andrews <candrews@integralblue.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ImapManager extends IoManager
{
    protected $conn = null;

    function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Fetch the singleton manager for the current site.
     * @return mixed ImapManager, or false if unneeded
     */
    public static function get()
    {
        // TRANS: Exception thrown when the ImapManager is used incorrectly in the code.
        throw new Exception(_m('ImapManager should be created using its constructor, not using the static "get()" method.'));
    }

    /**
     * Lists the IM connection socket to allow i/o master to wake
     * when input comes in here as well as from the queue source.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        return array();
    }

    /**
     * Tell the i/o master we need one instance globally.
     * Since this is a plugin manager, the plugin class itself will
     * create one instance per site. This prevents the IoMaster from
     * making more instances.
     */
    public static function multiSite()
    {
        return IoManager::GLOBAL_SINGLE_ONLY;
    }

    /**
     * Initialize connection to server.
     * @return boolean true on success
     */
    public function start($master)
    {
        if(parent::start($master))
        {
            $this->conn = $this->connect();
            return true;
        }else{
            return false;
        }
    }

    public function handleInput($socket)
    {
        $this->check_mailbox();
        return true;
    }

    public function poll()
    {
        return $this->check_mailbox() > 0;
    }

    function pollInterval()
    {
        return $this->plugin->poll_frequency;
    }

    protected function connect()
    {
        $this->conn = imap_open($this->plugin->mailbox, $this->plugin->user, $this->plugin->password);
        if($this->conn){
            common_log(LOG_INFO, "Connected");
            return $this->conn;
        }else{
            common_log(LOG_INFO, "Failed to connect: " . imap_last_error());
            return $this->conn;
        }
    }

    protected function check_mailbox()
    {
        imap_ping($this->conn);
        $count = imap_num_msg($this->conn);
        common_log(LOG_INFO, "Found $count messages");
        if($count > 0){
            $handler = new IMAPMailHandler();
            for($i=1; $i <= $count; $i++)
            {
                $rawmessage = imap_fetchheader($this->conn, $count, FT_PREFETCHTEXT) . imap_body($this->conn, $i);
                $handler->handle_message($rawmessage);
                imap_delete($this->conn, $i);
            }
            imap_expunge($this->conn);
            common_log(LOG_INFO, "Finished processing messages");
        }
        return $count;
    }
}

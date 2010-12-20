<?php
/**
 * StatusNet - the distributed open-source microblogging tool
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
 * Extends the Streams driver (Phergie_Driver_Streams) to give external access
 * to the socket resources and send method
 *
 * @category  Phergie
 * @package   Phergie_Driver_Statusnet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Phergie_Driver_Statusnet extends Phergie_Driver_Streams {
    /**
     * Handles construction of command strings and their transmission to the
     * server.
     *
     * @param string       $command Command to send
     * @param string|array $args    Optional string or array of sequential
     *        arguments
     *
     * @return string Command string that was sent
     * @throws Phergie_Driver_Exception
     */
    public function send($command, $args = '') {
        return parent::send($command, $args);
    }

    public function forceQuit() {
        try {
            // Send a QUIT command to the server
            $this->send('QUIT', 'Reconnecting');
        } catch (Phergie_Driver_Exception $e){}

        // Terminate the socket connection
        fclose($this->socket);

        // Remove the socket from the internal socket list
        unset($this->sockets[(string) $this->getConnection()->getHostmask()]);
    }

    /**
    * Returns the array of sockets
    *
    * @return array Array of socket resources
    */
    public function getSockets() {
        return $this->sockets;
    }
}
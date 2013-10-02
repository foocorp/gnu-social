<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Instead of sending IRC messages, retrieve the raw data that would be sent
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
 * @category  Network
 * @package   StatusNet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class FakeIrc extends Phergie_Driver_Streams {
    public $would_be_sent = null;

    /**
    * Store the components for sending a command
    *
    * @param string $command Command
    * @param array $args Arguments
    * @return void
    */
    protected function send($command, $args = '') {
        $this->would_be_sent = array('command' => $command, 'args' => $args);
    }
}

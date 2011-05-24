<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Extend the IMChannel class to allow commands to send messages
 * to a channel instead of PMing a user
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

class ChannelResponseChannel extends IMChannel {
    protected $ircChannel;

    /**
    * Construct a ChannelResponseChannel
    *
    * @param IMplugin $imPlugin IMPlugin
    * @param string $ircChannel IRC Channel to reply to
    * @return ChannelResponseChannel
    */
    public function __construct($imPlugin, $ircChannel) {
        $this->ircChannel = $ircChannel;
        parent::__construct($imPlugin);
    }

    /**
    * Send a message using the plugin
    *
    * @param User $user User
    * @param string $text Message text
    * @return void
    */
    public function output($user, $text) {
        $text = $user->nickname.': ['.common_config('site', 'name') . '] ' . $text;
        $this->imPlugin->sendMessage($this->ircChannel, $text);
    }
}

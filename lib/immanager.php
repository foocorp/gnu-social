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
 * IKM background connection manager for IM-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has IM enabled.
 *
 * Implementations that extend this class will likely want to:
 * 1) override start() with their connection process.
 * 2) override handleInput() with what to do when data is waiting on
 *    one of the sockets
 * 3) override idle($timeout) to do keepalives (if necessary)
 * 4) implement send_raw_message() to send raw data that ImPlugin::enqueueOutgoingRaw
 *      enqueued
 */

abstract class ImManager extends IoManager
{
    abstract function send_raw_message($data);

    function __construct($imPlugin)
    {
        $this->plugin = $imPlugin;
        $this->plugin->imManager = $this;
    }

    /**
     * Fetch the singleton manager for the current site.
     * @return mixed ImManager, or false if unneeded
     */
    public static function get()
    {
        throw new Exception('ImManager should be created using it\'s constructor, not the static get method');
    }
}

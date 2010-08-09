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
 * Base class for queue handlers.
 *
 * As of 0.9, queue handlers are short-lived for items as they are
 * dequeued by a QueueManager running in an IoMaster in a daemon
 * such as queuedaemon.php.
 *
 * Extensions requiring long-running maintenance or polling should
 * register an IoManager.
 *
 * Subclasses must override at least the following methods:
 * - transport
 * - handle
 */
class QueueHandler
{

    /**
     * Here's the meat of your queue handler -- you're handed a Notice
     * or other object, which you may do as you will with.
     *
     * If this function indicates failure, a warning will be logged
     * and the item is placed back in the queue to be re-run.
     *
     * @param mixed $object
     * @return boolean true on success, false on failure
     */
    function handle($object)
    {
        return true;
    }
}


<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Queue handler for pushing new notices to OpenMicroBlogging subscribers.
 */
class OmbQueueHandler extends QueueHandler
{

    function transport()
    {
        return 'omb';
    }

    /**
     * @fixme doesn't currently report failure back to the queue manager
     * because omb_broadcast_notice() doesn't report it to us
     */
    function handle($notice)
    {
        if ($this->is_remote($notice)) {
            common_log(LOG_DEBUG, 'Ignoring remote notice ' . $notice->id);
            return true;
        } else {
            require_once(dirname(__FILE__) . '/omb.php');
            omb_broadcast_notice($notice);
            return true;
        }
    }

    function is_remote($notice)
    {
        $user = User::staticGet($notice->profile_id);
        return is_null($user);
    }
}

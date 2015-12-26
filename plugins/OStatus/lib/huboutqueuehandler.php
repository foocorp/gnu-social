<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Send a raw PuSH atom update from our internal hub.
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubOutQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'hubout';
    }

    function handle($data)
    {
        $sub = $data['sub'];
        $atom = $data['atom'];
        $retries = $data['retries'];

        assert($sub instanceof HubSub);
        assert(is_string($atom));

        try {
            $sub->push($atom);
        } catch (Exception $e) {
            $retries--;
            $msg = "Failed PuSH to $sub->callback for $sub->topic: " .
                   $e->getMessage();
            if ($retries > 0) {
                common_log(LOG_INFO, "$msg; scheduling for $retries more tries");

                // @fixme when we have infrastructure to schedule a retry
                // after a delay, use it.
                $sub->distribute($atom, $retries);
            } else {
                common_log(LOG_ERR, "$msg; discarding");
            }
        }

        return true;
    }
}

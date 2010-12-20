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
 * Send a PuSH subscription verification from our internal hub.
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubConfQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'hubconf';
    }

    function handle($data)
    {
        $sub = $data['sub'];
        $mode = $data['mode'];
        $token = $data['token'];

        assert($sub instanceof HubSub);
        assert($mode === 'subscribe' || $mode === 'unsubscribe');

        common_log(LOG_INFO, __METHOD__ . ": $mode $sub->callback $sub->topic");
        try {
            $sub->verify($mode, $token);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Failed PuSH $mode verify to $sub->callback for $sub->topic: " .
                                $e->getMessage());
            // @fixme schedule retry?
            // @fixme just kill it?
        }

        return true;
    }
}

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
 * When we have a large batch of PuSH consumers, we break the data set
 * into smaller chunks. Enqueue final destinations...
 *
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubPrepQueueHandler extends QueueHandler
{
    // Enqueue this many low-level distributions before re-queueing the rest
    // of the batch to be processed later. Helps to keep latency down for other
    // things happening during a particularly long OStatus delivery session.
    //
    // [Could probably ditch this if we had working message delivery priorities
    // for queueing, but this isn't supported in ActiveMQ 5.3.]
    const ROLLING_BATCH = 20;

    function transport()
    {
        return 'hubprep';
    }

    function handle($data)
    {
        $topic = $data['topic'];
        $atom = $data['atom'];
        $pushCallbacks = $data['pushCallbacks'];

        assert(is_string($atom));
        assert(is_string($topic));
        assert(is_array($pushCallbacks));

        // Set up distribution for the first n subscribing sites...
        // If we encounter an uncatchable error, queue handling should
        // automatically re-run the batch, which could lead to some dupe
        // distributions.
        //
        // Worst case is if one of these hubprep entries dies too many
        // times and gets dropped; the rest of the batch won't get processed.
        try {
            $n = 0;
            while (count($pushCallbacks) && $n < self::ROLLING_BATCH) {
                $n++;
                $callback = array_shift($pushCallbacks);
                $sub = HubSub::getKV($topic, $callback);
                if (!$sub) {
                    common_log(LOG_ERR, "Skipping PuSH delivery for deleted(?) consumer $callback on $topic");
                    continue;
                }

                $sub->distribute($atom);
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, "Exception during PuSH batch out: " .
                                $e->getMessage() .
                                " prepping $topic to $callback");
        }

        // And re-queue the rest of the batch!
        if (count($pushCallbacks) > 0) {
            $sub = new HubSub();
            $sub->topic = $topic;
            $sub->bulkDistribute($atom, $pushCallbacks);
        }

        return true;
    }
}

<?php
/*
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
 * Renew an expiring feedsub
 * @package FeedSub
 * @author Stephen Paul Weber <singpolyma@singpolyma.net>
 */
class PushRenewQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'pushrenew';
    }

    function handle($data)
    {
        $feedsub_id = $data['feedsub_id'];
        $feedsub = FeedSub::getKV('id', $feedsub_id);
        if ($feedsub instanceof FeedSub) {
            try {
                common_log(LOG_INFO, "Renewing feed subscription\n\tExp.: {$feedsub->sub_end}\n\tFeed: {$feedsub->uri}\n\tHub:  {$feedsub->huburi}");
                $feedsub->renew();
            } catch(Exception $e) {
                common_log(LOG_ERR, "Exception during PuSH renew processing for $feedsub->uri: " . $e->getMessage());
            }
        } else {
            common_log(LOG_ERR, "Discarding renew for unknown feed subscription id $feedsub_id");
        }
        return true;
    }
}

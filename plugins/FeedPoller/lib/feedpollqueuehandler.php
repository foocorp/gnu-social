<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Poll a feed based on its urlhash, the full url is in the feedsub table
 *
 * @author Mikael Nordfeldth <mmn@hethane.se>
 */
class FeedPollQueueHandler extends QueueHandler
{
    public function transport()
    {
        return FeedPoll::QUEUE_CHECK;
    }

    public function handle($item)
    {
        $feedsub = FeedSub::getKV('id', $item['id']);
        if (!$feedsub instanceof FeedSub) {
            // Removed from the feedsub table I guess
            return true;
        }
        if (!$feedsub->sub_state == 'nohub') {
            // We're not supposed to poll this (either it's PuSH or it's unsubscribed)
            return true;
        }

        try {
            FeedPoll::checkUpdates($feedsub);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Failed to check feedsub id= ".$feedsub->id.' ("'.$e->getMessage().'")');
        }

        return true;
    }
}

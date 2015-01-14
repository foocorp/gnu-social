<?php
/**
 * Store last poll time in db, then check if they should be renewed (if so, enqueue).
 * Can be called from a queue handler on a per-feed status to poll stuff.
 *
 * Used as internal feed polling mechanism (atom/rss)
 *
 * @category OStatus
 * @package  GNUsocial
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class FeedPoll {
    const DEFAULT_INTERVAL = 5; // in minutes

    const QUEUE_CHECK = 'feedpoll-check';

    // TODO: Find some smart way to add feeds only once, so they don't get more than 1 feedpoll in the queue each
    //       probably through sub_start sub_end trickery.
    public static function enqueueNewFeeds(array $args=array()) {
        if (!isset($args['interval']) || !is_int($args['interval']) || $args['interval']<=0) {
            $args['interval'] = self::DEFAULT_INTERVAL;
        }

        $args['interval'] *= 60;    // minutes to seconds

        $feedsub = new FeedSub();
        $feedsub->sub_state = 'nohub';
        // Find feeds that haven't been polled within the desired interval,
        // though perhaps we're abusing the "last_update" field here?
        $feedsub->whereAdd(sprintf('last_update < "%s"', common_sql_date(time()-$args['interval'])));
        $feedsub->find();

        $qm = QueueManager::get();
        while ($feedsub->fetch()) {
            $orig = clone($feedsub);
            $item = array('id' => $feedsub->id);
            $qm->enqueue($item, self::QUEUE_CHECK);
            common_debug('Enqueueing FeedPoll feeds, currently: '.$feedsub->uri);
            $feedsub->last_update = common_sql_now();
            $feedsub->update($orig);
        }
    }

    public function setupFeedSub(FeedSub $feedsub, $interval=300)
    {
        $orig = clone($feedsub);
        $feedsub->sub_state = 'nohub';
        $feedsub->sub_start = common_sql_date(time());
        $feedsub->sub_end   = '';
        $feedsub->last_update = common_sql_date(time()-$interval);  // force polling as soon as we can
        $feedsub->update($orig);
    }

    public function checkUpdates(FeedSub $feedsub)
    {
        $request = new HTTPClient();
        common_debug('Enqueueing FeedPoll feeds went well, now checking updates for: '.$feedsub->getUri());
        $feed = $request->get($feedsub->uri);
        if (!$feed->isOk()) {
            throw new ServerException('FeedSub could not fetch id='.$feedsub->id.' (Error '.$feed->getStatus().': '.$feed->getBody());
        }
        $feedsub->receive($feed->getBody(), null);
    }
}

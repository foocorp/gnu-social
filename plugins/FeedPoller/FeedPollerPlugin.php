<?php
/**
 * GNU social feed polling plugin, to avoid using external PuSH hubs
 *
 * @category  Feed
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class FeedPollerPlugin extends Plugin {
    public $interval = 5;   // interval in minutes for feed checks

    public function onEndInitializeQueueManager(QueueManager $qm)
    {
        $qm->connect(FeedPoll::QUEUE_CHECK, 'FeedPollQueueHandler');
        return true;
    }

    public function onCronMinutely()
    {
        $args = array('interval'=>$this->interval);
        FeedPoll::enqueueNewFeeds($args);
        return true;
    }

    public function onFeedSubscribe(FeedSub $feedsub)
    {
        if (!$feedsub->isPuSH()) {
            FeedPoll::setupFeedSub($feedsub, $this->interval*60);
            return false;   // We're polling this feed, so stop processing FeedSubscribe
        }
        return true;
    }

    public function onFeedUnsubscribe(FeedSub $feedsub)
    {
        if (!$feedsub->isPuSH()) {
            // removes sub_state setting and such
            $feedsub->confirmUnsubscribe();
            return false;
        }
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'FeedPoller',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://www.gnu.org/software/social/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Feed polling plugin to avoid using external push hubs.'));
        return true;
    }
}

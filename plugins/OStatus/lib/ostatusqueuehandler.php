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
 * Prepare PuSH and Salmon distributions for an outgoing message.
 *
 * @package OStatusPlugin
 * @author Brion Vibber <brion@status.net>
 */
class OStatusQueueHandler extends QueueHandler
{
    // If we have more than this many subscribing sites on a single feed,
    // break up the PuSH distribution into smaller batches which will be
    // rolled into the queue progressively. This reduces disruption to
    // other, shorter activities being enqueued while we work.
    const MAX_UNBATCHED = 50;

    // Each batch (a 'hubprep' entry) will have this many items.
    // Selected to provide a balance between queue packet size
    // and number of batches that will end up getting processed.
    // For 20,000 target sites, 1000 should work acceptably.
    const BATCH_SIZE = 1000;

    function transport()
    {
        return 'ostatus';
    }

    function handle($notice)
    {
        assert($notice instanceof Notice);

        $this->notice = $notice;
        $this->user = User::getKV('id', $notice->profile_id);

        try {
            $profile = $this->notice->getProfile();
        } catch (Exception $e) {
            common_log(LOG_ERR, "Can't get profile for notice; skipping: " . $e->getMessage());
            return true;
        }

        if ($notice->isLocal()) {
            // Notices generated on remote sites will have already
            // been pushed to user's subscribers by their origin sites.
            $this->pushUser();
        }

        foreach ($notice->getAttentionProfiles() as $target) {
            common_debug("OSTATUS [{$this->notice->getID()}]: Attention target profile {$target->getNickname()} ({$target->getID()})");
            if ($target->isGroup()) {
                common_debug("OSTATUS [{$this->notice->getID()}]: {$target->getID()} is a group");
                $oprofile = Ostatus_profile::getKV('group_id', $target->getGroup()->getID());
                if (!$oprofile instanceof Ostatus_profile) {
                    // we don't save profiles like this yet, but in the future
                    $oprofile = Ostatus_profile::getKV('profile_id', $target->getID());
                }
                if ($oprofile instanceof Ostatus_profile) {
                    // remote group
                    if ($notice->isLocal()) {
                        common_debug("OSTATUS [{$this->notice->getID()}]: notice is local and remote group with profile ID {$target->getID()} gets a ping");
                        $this->pingReply($oprofile);
                    }
                } else {
                    common_debug("OSTATUS [{$this->notice->getID()}]: local group with profile id {$target->getID()} gets pushed out");
                    // local group
                    $this->pushGroup($target->getGroup());
                }
            } elseif ($notice->isLocal()) {
                // Notices generated on other sites will have already
                // pinged their reply-targets, so only do these things
                // if the target is not a group and the notice is locally generated

                $oprofile = Ostatus_profile::getKV('profile_id', $target->getID());
                if ($oprofile instanceof Ostatus_profile) {
                    common_debug("OSTATUS [{$this->notice->getID()}]: Notice is local and {$target->getID()} is remote profile, getting pingReply");
                    $this->pingReply($oprofile);
                }
            }
        }

        if ($notice->isLocal()) {
            try {
                $parent = $this->notice->getParent();
                foreach($parent->getAttentionProfiles() as $related) {
                    if ($related->isGroup()) {
                        // don't ping groups in parent notices since we might not be a member of them,
                        // though it could be useful if we study this and use it correctly
                        continue;
                    }
                    common_debug("OSTATUS [{$this->notice->getID()}]: parent notice {$parent->getID()} has related profile id=={$related->getID()}");
                    // FIXME: don't ping twice in case someone is in both notice attention spans!
                    $oprofile = Ostatus_profile::getKV('profile_id', $related->getID());
                    if ($oprofile instanceof Ostatus_profile) {
                        $this->pingReply($oprofile);
                    }
                }
            } catch (NoParentNoticeException $e) {
                // nothing to do then
            }

            foreach ($notice->getProfileTags() as $ptag) {
                $oprofile = Ostatus_profile::getKV('peopletag_id', $ptag->id);
                if (!$oprofile) {
                    $this->pushPeopletag($ptag);
                }
            }
        }

        return true;
    }

    function pushUser()
    {
        if ($this->user) {
            common_debug("OSTATUS [{$this->notice->getID()}]: pushing feed for local user {$this->user->getID()}");
            // For local posts, ping the PuSH hub to update their feed.
            // http://identi.ca/api/statuses/user_timeline/1.atom
            $feed = common_local_url('ApiTimelineUser',
                                     array('id' => $this->user->id,
                                           'format' => 'atom'));
            $this->pushFeed($feed, array($this, 'userFeedForNotice'));
        }
    }

    function pushGroup(User_group $group)
    {
        common_debug("OSTATUS [{$this->notice->getID()}]: pushing group '{$group->getNickname()}' profile_id={$group->profile_id}");
        // For a local group, ping the PuSH hub to update its feed.
        // Updates may come from either a local or a remote user.
        $feed = common_local_url('ApiTimelineGroup',
                                 array('id' => $group->getID(),
                                       'format' => 'atom'));
        $this->pushFeed($feed, array($this, 'groupFeedForNotice'), $group->getID());
    }

    function pushPeopletag($ptag)
    {
        common_debug("OSTATUS [{$this->notice->getID()}]: pushing peopletag '{$ptag->id}'");
        // For a local people tag, ping the PuSH hub to update its feed.
        // Updates may come from either a local or a remote user.
        $feed = common_local_url('ApiTimelineList',
                                 array('id' => $ptag->id,
                                       'user' => $ptag->tagger,
                                       'format' => 'atom'));
        $this->pushFeed($feed, array($this, 'peopletagFeedForNotice'), $ptag);
    }

    function pingReply(Ostatus_profile $oprofile)
    {
        if ($this->user) {
            common_debug("OSTATUS [{$this->notice->getID()}]: pinging reply to {$oprofile->localProfile()->getNickname()} for local user '{$this->user->getID()}'");
            // For local posts, send a Salmon ping to the mentioned
            // remote user or group.
            // @fixme as an optimization we can skip this if the
            // remote profile is subscribed to the author.
            $oprofile->notifyDeferred($this->notice, $this->user);
        }
    }

    /**
     * @param string $feed URI to the feed
     * @param callable $callback function to generate Atom feed update if needed
     *        any additional params are passed to the callback.
     */
    function pushFeed($feed, $callback)
    {
        $hub = common_config('ostatus', 'hub');
        if ($hub) {
            $this->pushFeedExternal($feed, $hub);
        }

        $sub = new HubSub();
        $sub->topic = $feed;
        if ($sub->find()) {
            $args = array_slice(func_get_args(), 2);
            $atom = call_user_func_array($callback, $args);
            $this->pushFeedInternal($atom, $sub);
        } else {
            common_log(LOG_INFO, "No PuSH subscribers for $feed");
        }
        return true;
    }

    /**
     * Ping external hub about this update.
     * The hub will pull the feed and check for new items later.
     * Not guaranteed safe in an environment with database replication.
     *
     * @param string $feed feed topic URI
     * @param string $hub PuSH hub URI
     * @fixme can consolidate pings for user & group posts
     */
    function pushFeedExternal($feed, $hub)
    {
        $client = new HTTPClient();
        try {
            $data = array('hub.mode' => 'publish',
                          'hub.url' => $feed);
            $response = $client->post($hub, array(), $data);
            if ($response->getStatus() == 204) {
                common_log(LOG_INFO, "PuSH ping to hub $hub for $feed ok");
                return true;
            } else {
                common_log(LOG_ERR, "PuSH ping to hub $hub for $feed failed with HTTP " .
                                    $response->getStatus() . ': ' .
                                    $response->getBody());
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, "PuSH ping to hub $hub for $feed failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue up direct feed update pushes to subscribers on our internal hub.
     * If there are a large number of subscriber sites, intermediate bulk
     * distribution triggers may be queued.
     *
     * @param string $atom update feed, containing only new/changed items
     * @param HubSub $sub open query of subscribers
     */
    function pushFeedInternal($atom, $sub)
    {
        common_log(LOG_INFO, "Preparing $sub->N PuSH distribution(s) for $sub->topic");
        $n = 0;
        $batch = array();
        while ($sub->fetch()) {
            $n++;
            if ($n < self::MAX_UNBATCHED) {
                $sub->distribute($atom);
            } else {
                $batch[] = $sub->callback;
                if (count($batch) >= self::BATCH_SIZE) {
                    $sub->bulkDistribute($atom, $batch);
                    $batch = array();
                }
            }
        }
        if (count($batch) > 0) {
            $sub->bulkDistribute($atom, $batch);
        }
    }

    /**
     * Build a single-item version of the sending user's Atom feed.
     * @return string
     */
    function userFeedForNotice()
    {
        $atom = new AtomUserNoticeFeed($this->user);
        $atom->addEntryFromNotice($this->notice);
        $feed = $atom->getString();

        return $feed;
    }

    function groupFeedForNotice($group_id)
    {
        $group = User_group::getKV('id', $group_id);

        $atom = new AtomGroupNoticeFeed($group);
        $atom->addEntryFromNotice($this->notice);
        $feed = $atom->getString();

        return $feed;
    }

    function peopletagFeedForNotice($ptag)
    {
        $atom = new AtomListNoticeFeed($ptag);
        $atom->addEntryFromNotice($this->notice);
        $feed = $atom->getString();

        return $feed;
    }
}

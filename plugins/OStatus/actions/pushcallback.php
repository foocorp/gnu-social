<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

/**
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PushCallbackAction extends Action
{
    protected function handle()
    {
        StatusNet::setApi(true); // Minimize error messages to aid in debugging
        parent::handle();
        if ($this->isPost()) {
            return $this->handlePost();
        }
        
        return $this->handleGet();
    }

    /**
     * Handler for POST content updates from the hub
     */
    function handlePost()
    {
        $feedid = $this->arg('feed');
        common_log(LOG_INFO, "POST for feed id $feedid");
        if (!$feedid) {
            // TRANS: Server exception thrown when referring to a non-existing or empty feed.
            throw new ServerException(_m('Empty or invalid feed id.'), 400);
        }

        $feedsub = FeedSub::getKV('id', $feedid);
        if (!$feedsub) {
            // TRANS: Server exception. %s is a feed ID.
            throw new ServerException(sprintf(_m('Unknown PuSH feed id %s'),$feedid), 400);
        }

        $hmac = '';
        if (isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
            $hmac = $_SERVER['HTTP_X_HUB_SIGNATURE'];
        }

        $post = file_get_contents('php://input');

        // Queue this to a background process; we should return
        // as quickly as possible from a distribution POST.
        // If queues are disabled this'll process immediately.
        $data = array('feedsub_id' => $feedsub->id,
                      'post' => $post,
                      'hmac' => $hmac);
        $qm = QueueManager::get();
        $qm->enqueue($data, 'pushin');
    }

    /**
     * Handler for GET verification requests from the hub.
     */
    function handleGet()
    {
        $mode = $this->arg('hub_mode');
        $topic = $this->arg('hub_topic');
        $challenge = $this->arg('hub_challenge');
        $lease_seconds = $this->arg('hub_lease_seconds');   // Must be >0 for PuSH 0.4!
        common_log(LOG_INFO, __METHOD__ . ": sub verification mode: $mode topic: $topic challenge: $challenge lease_seconds: $lease_seconds");

        if ($mode != 'subscribe' && $mode != 'unsubscribe') {
            // TRANS: Client exception. %s is an invalid value for hub.mode.
            throw new ClientException(sprintf(_m('Bad hub.mode "$s".',$mode)), 404);
        }

        $feedsub = FeedSub::getKV('uri', $topic);
        if (!$feedsub instanceof FeedSub) {
            // TRANS: Client exception. %s is an invalid feed name.
            throw new ClientException(sprintf(_m('Bad hub.topic feed "%s".'),$topic), 404);
        }

        if ($mode == 'subscribe') {
            // We may get re-sub requests legitimately.
            if ($feedsub->sub_state != 'subscribe' && $feedsub->sub_state != 'active') {
                // TRANS: Client exception. %s is an invalid topic.
                throw new ClientException(sprintf(_m('Unexpected subscribe request for %s.'),$topic), 404);
            }
        } else {
            if ($feedsub->sub_state != 'unsubscribe') {
                // TRANS: Client exception. %s is an invalid topic.
                throw new ClientException(sprintf(_m('Unexpected unsubscribe request for %s.'),$topic), 404);
            }
        }

        if ($mode == 'subscribe') {
            if ($feedsub->sub_state == 'active') {
                common_log(LOG_INFO, __METHOD__ . ': sub update confirmed');
            } else {
                common_log(LOG_INFO, __METHOD__ . ': sub confirmed');
            }
            $feedsub->confirmSubscribe($lease_seconds);
        } else {
            common_log(LOG_INFO, __METHOD__ . ": unsub confirmed; deleting sub record for $topic");
            $feedsub->confirmUnsubscribe();
        }
        print $challenge;
    }
}

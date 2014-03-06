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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Queue handler to deal with incoming Twitter status updates, as retrieved by
 * TwitterDaemon (twitterdaemon.php).
 *
 * The queue handler passes the status through TwitterImporter for import into the
 * local database (if necessary), then adds the imported notice to the local inbox
 * of the attached Twitter user.
 *
 * Warning: the way we do inbox distribution manually means that realtime, XMPP, etc
 * don't work on Twitter-borne messages. When TwitterImporter is changed to handle
 * that correctly, we'll only need to do this once...?
 */
class TweetInQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'tweetin';
    }

    function handle($data)
    {
        // JSON object with Twitter data
        $status = $data['status'];

        // Twitter user ID this incoming data belongs to.
        $receiver = $data['for_user'];

        $importer = new TwitterImport();
        $notice = $importer->importStatus($status);
        if ($notice instanceof Notice) {
            $flink = Foreign_link::getByForeignID($receiver, TWITTER_SERVICE);
            if ($flink instanceof Foreign_link) {
                common_log(LOG_DEBUG, "TweetInQueueHandler - Got flink so add notice ".
                           $notice->id." to inbox ".$flink->user_id);
                // FIXME: How should a Twitter user get their Inbox filled with foreign tweets?
            } else {
               common_log(LOG_DEBUG, "TweetInQueueHandler - No flink found for foreign user ".$receiver);
            }
        }

        return true;
    }
}

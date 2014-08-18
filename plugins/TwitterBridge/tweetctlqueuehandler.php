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

require_once __DIR__ . '/twitter.php';

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
class TweetCtlQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'tweetctl';
    }

    function handle($data)
    {
        // A user has activated or deactivated their Twitter bridge
        // import status.
        $action = $data['action'];
        $userId = $data['for_user'];

        $tm = TwitterManager::get();
        if ($action == 'start') {
            $tm->startTwitterUser($userId);
        } else if ($action == 'stop') {
            $tm->stopTwitterUser($userId);
        }

        return true;
    }
}

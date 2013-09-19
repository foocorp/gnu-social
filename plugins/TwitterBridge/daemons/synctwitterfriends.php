#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'di::';
$longoptions = array('id::', 'debug');

$helptext = <<<END_OF_TRIM_HELP
Batch script for synching local friends with Twitter friends.
  -i --id              Identity (default 'generic')
  -d --debug           Debug (lots of log output)

END_OF_TRIM_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/lib/parallelizingdaemon.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitteroauthclient.php';

/**
 * Daemon to sync local friends with Twitter friends
 *
 * @category Twitter
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SyncTwitterFriendsDaemon extends ParallelizingDaemon
{
    /**
     *  Constructor
     *
     * @param string  $id           the name/id of this daemon
     * @param int     $interval     sleep this long before doing everything again
     * @param int     $max_children maximum number of child processes at a time
     * @param boolean $debug        debug output flag
     *
     * @return void
     *
     **/
    function __construct($id = null, $interval = 60,
                         $max_children = 2, $debug = null)
    {
        parent::__construct($id, $interval, $max_children, $debug);
    }

    /**
     * Name of this daemon
     *
     * @return string Name of the daemon.
     */
    function name()
    {
        return ('synctwitterfriends.' . $this->_id);
    }

    /**
     * Find all the Twitter foreign links for users who have requested
     * automatically subscribing to their Twitter friends locally.
     *
     * @return array flinks an array of Foreign_link objects
     */
    function getObjects()
    {
        $flinks = array();
        $flink = new Foreign_link();

        $conn = &$flink->getDatabaseConnection();

        $flink->service = TWITTER_SERVICE;
        $flink->orderBy('last_friendsync');
        $flink->limit(25);  // sync this many users during this run
        $flink->find();

        while ($flink->fetch()) {
            if (($flink->friendsync & FOREIGN_FRIEND_RECV) == FOREIGN_FRIEND_RECV) {
                $flinks[] = clone($flink);
            }
        }

        $conn->disconnect();

        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        return $flinks;
    }

    function childTask($flink) {
        // Each child ps needs its own DB connection

        // Note: DataObject::getDatabaseConnection() creates
        // a new connection if there isn't one already
        $conn = &$flink->getDatabaseConnection();

        $this->subscribeTwitterFriends($flink);

        $flink->last_friendsync = common_sql_now();
        $flink->update();

        $conn->disconnect();

        // XXX: Couldn't find a less brutal way to blow
        // away a cached connection
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);
    }

    function fetchTwitterFriends($flink)
    {
        $friends = array();

        $client = null;

        if (TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);
            common_debug($this->name() . '- Grabbing friends IDs with OAuth.');
        } else {
            common_debug("Skipping Twitter friends for {$flink->user_id} since not OAuth.");
            return $friends;
        }

        try {
            $friends_ids = $client->friendsIds();
        } catch (Exception $e) {
            common_log(LOG_WARNING, $this->name() .
                       ' - error getting friend ids: ' .
                       $e->getMessage());
            return $friends;
        }

        if (empty($friends_ids)) {
            common_debug($this->name() .
                         " - Twitter user $flink->foreign_id " .
                         'doesn\'t have any friends!');
            return $friends;
        }

        common_debug($this->name() . ' - Twitter\'s API says Twitter user id ' .
                     "$flink->foreign_id has " .
                     count($friends_ids) . ' friends.');

        // Calculate how many pages to get...
        $pages = ceil(count($friends_ids) / 100);

        if ($pages == 0) {
            common_debug($this->name() . " - $user seems to have no friends.");
        }

        for ($i = 1; $i <= $pages; $i++) {

        try {
            $more_friends = $client->statusesFriends(null, null, null, $i);
        } catch (Exception $e) {
            common_log(LOG_WARNING, $this->name() .
                       ' - cURL error getting Twitter statuses/friends ' .
                       "page $i - " . $e->getCode() . ' - ' .
                       $e->getMessage());
        }

            if (empty($more_friends)) {
                common_log(LOG_WARNING, $this->name() .
                           " - Couldn't retrieve page $i " .
                           "of Twitter user $flink->foreign_id friends.");
                continue;
            } else {
                $friends = array_merge($friends, $more_friends);
            }
        }

        return $friends;
    }

    function subscribeTwitterFriends($flink)
    {
        $friends = $this->fetchTwitterFriends($flink);

        if (empty($friends)) {
            common_debug($this->name() .
                         ' - Couldn\'t get friends from Twitter for ' .
                         "Twitter user $flink->foreign_id.");
            return false;
        }

        $profile = $flink->getProfile();

        foreach ($friends as $friend) {

            $friend_name = $friend->screen_name;
            $friend_id = (int) $friend->id;

            // Update or create the Foreign_user record for each
            // Twitter friend

            if (!save_twitter_user($friend_id, $friend_name)) {
                common_log(LOG_WARNING, $this->name() .
                           " - Couldn't save $screen_name's friend, $friend_name.");
                continue;
            }

            // Check to see if there's a related local user

            $friend_flink = Foreign_link::getByForeignID($friend_id,
                                                         TWITTER_SERVICE);

            if (!empty($friend_flink)) {

                // Get associated user and subscribe her

                $friend_profile = Profile::getKV('id', $friend_flink->user_id);

                if ($friend_profile instanceof Profile) {
                    try {
                        $other = Profile::getKV('id', $invites->user_id);
                        Subscription::start($profile, $friend_profile);
                        common_log(LOG_INFO,
                                   $this->name() . ' - Subscribed ' .
                                   "{$friend_profile->nickname} to {$profile->nickname}.");
                    } catch (Exception $e) {
                        common_debug($this->name() .
                                     ' - Tried and failed subscribing ' .
                                     "{$friend_profile->nickname} to {$profile->nickname} - " .
                                     $e->getMessage());
                    }
                }
            }
        }

        return true;
    }

}

$id    = null;
$debug = null;

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

if (have_option('d') || have_option('debug')) {
    $debug = true;
}

$syncer = new SyncTwitterFriendsDaemon($id, 60, 2, $debug);
$syncer->runOnce();

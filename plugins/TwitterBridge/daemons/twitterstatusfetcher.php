#!/usr/bin/env php
<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
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

// Tune number of processes and how often to poll Twitter
// XXX: Should these things be in config.php?
define('MAXCHILDREN', 2);
define('POLL_INTERVAL', 60); // in seconds

$shortoptions = 'di::';
$longoptions = array('id::', 'debug');

$helptext = <<<END_OF_TRIM_HELP
Batch script for retrieving Twitter messages from foreign service.

  -i --id              Identity (default 'generic')
  -d --debug           Debug (lots of log output)

END_OF_TRIM_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/lib/daemon.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitteroauthclient.php';

/**
 * Fetch statuses from Twitter
 *
 * Fetches statuses from Twitter and inserts them as notices
 *
 * NOTE: an Avatar path MUST be set in config.php for this
 * script to work, e.g.:
 *     $config['avatar']['path'] = $config['site']['path'] . '/avatar/';
 *
 * @todo @fixme @gar Fix the above. For some reason $_path is always empty when
 * this script is run, so the default avatar path is always set wrong in
 * default.php. Therefore it must be set explicitly in config.php. --Z
 *
 * @category Twitter
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TwitterStatusFetcher extends ParallelizingDaemon
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
        return ('twitterstatusfetcher.'.$this->_id);
    }

    /**
     * Find all the Twitter foreign links for users who have requested
     * importing of their friends' timelines
     *
     * @return array flinks an array of Foreign_link objects
     */
    function getObjects()
    {
        global $_DB_DATAOBJECT;
        $flink = new Foreign_link();
        $conn = &$flink->getDatabaseConnection();

        $flink->service = TWITTER_SERVICE;
        $flink->orderBy('last_noticesync');
        $flink->find();

        $flinks = array();

        while ($flink->fetch()) {

            if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
                FOREIGN_NOTICE_RECV) {
                $flinks[] = clone($flink);
                common_log(LOG_INFO, "sync: foreign id $flink->foreign_id");
            } else {
                common_log(LOG_INFO, "nothing to sync");
            }
        }

        $flink->free();
        unset($flink);

        $conn->disconnect();
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        return $flinks;
    }

    function childTask($flink) {
        // Each child ps needs its own DB connection

        // Note: DataObject::getDatabaseConnection() creates
        // a new connection if there isn't one already
        $conn = &$flink->getDatabaseConnection();

        $this->getTimeline($flink);

        $flink->last_friendsync = common_sql_now();
        $flink->update();

        $conn->disconnect();

        // XXX: Couldn't find a less brutal way to blow
        // away a cached connection
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);
    }

    function getTimeline($flink)
    {
        if (empty($flink)) {
            common_log(LOG_WARNING, $this->name() .
                       " - Can't retrieve Foreign_link for foreign ID $fid");
            return;
        }

        common_debug($this->name() . ' - Trying to get timeline for Twitter user ' .
                     $flink->foreign_id);

        $client = null;

        if (TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);
            common_debug($this->name() . ' - Grabbing friends timeline with OAuth.');
        } else {
            common_debug("Skipping friends timeline for $flink->foreign_id since not OAuth.");
        }

        $timeline = null;

        $lastId = Twitter_synch_status::getLastId($flink->foreign_id, 'home_timeline');

        common_debug("Got lastId value '{$lastId}' for foreign id '{$flink->foreign_id}' and timeline 'home_timeline'");

        try {
            $timeline = $client->statusesHomeTimeline($lastId);
        } catch (Exception $e) {
            common_log(LOG_WARNING, $this->name() .
                       ' - Twitter client unable to get friends timeline for user ' .
                       $flink->user_id . ' - code: ' .
                       $e->getCode() . 'msg: ' . $e->getMessage());
        }

        if (empty($timeline)) {
            common_log(LOG_WARNING, $this->name() .  " - Empty timeline.");
            return;
        }

        common_debug(LOG_INFO, $this->name() . ' - Retrieved ' . sizeof($timeline) . ' statuses from Twitter.');

        $importer = new TwitterImport();

        // Reverse to preserve order

        foreach (array_reverse($timeline) as $status) {
            $notice = $importer->importStatus($status);

            if (!empty($notice)) {
                Inbox::insertNotice($flink->user_id, $notice->id);
            }
        }

        if (!empty($timeline)) {
            $lastId = twitter_id($timeline[0]);
            Twitter_synch_status::setLastId($flink->foreign_id, 'home_timeline', $lastId);
            common_debug("Set lastId value '$lastId' for foreign id '{$flink->foreign_id}' and timeline 'home_timeline'");
        }

        // Okay, record the time we synced with Twitter for posterity
        $flink->last_noticesync = common_sql_now();
        $flink->update();
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

$fetcher = new TwitterStatusFetcher($id, 60, 2, $debug);
$fetcher->runOnce();

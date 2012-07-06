#!/usr/bin/env php
<?php
/*
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'fi::a';
$longoptions = array('id::', 'foreground', 'all');

$helptext = <<<END_OF_TWITTERDAEMON_HELP
Daemon script for receiving new notices from Twitter users.

    -i --id           Identity (default none)
    -a --all          Handle Twitter for all local sites
                      (requires Stomp queue handler, status_network setup)
    -f --foreground   Stay in the foreground (default background)

END_OF_TWITTERDAEMON_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

class TwitterDaemon extends SpawningDaemon
{
    protected $allsites = false;

    function __construct($id=null, $daemonize=true, $threads=1, $allsites=false)
    {
        if ($threads != 1) {
            // This should never happen. :)
            throw new Exception("TwitterDaemon must run single-threaded");
        }
        parent::__construct($id, $daemonize, $threads);
        $this->allsites = $allsites;
    }

    function runThread()
    {
        common_log(LOG_INFO, 'Waiting to listen to Twitter and queues');

        $master = new TwitterMaster($this->get_id(), $this->processManager());
        $master->init($this->allsites);
        $master->service();

        common_log(LOG_INFO, 'terminating normally');

        return $master->respawn ? self::EXIT_RESTART : self::EXIT_SHUTDOWN;
    }

}

class TwitterMaster extends IoMaster
{
    protected $processManager;

    function __construct($id, $processManager)
    {
        parent::__construct($id);
        $this->processManager = $processManager;
    }

    /**
     * Initialize IoManagers for the currently configured site
     * which are appropriate to this instance.
     */
    function initManagers()
    {
        $qm = QueueManager::get();
        $qm->setActiveGroup('twitter');
        $this->instantiate($qm);
        $this->instantiate(new TwitterManager());
        $this->instantiate($this->processManager);
    }
}


class TwitterManager extends IoManager
{
    // Recommended resource limits from http://dev.twitter.com/pages/site_streams
    const MAX_STREAMS = 1000;
    const USERS_PER_STREAM = 100;
    const STREAMS_PER_SECOND = 20;

    protected $streams;
    protected $users;

    /**
     * Pull the site's active Twitter-importing users and start spawning
     * some data streams for them!
     *
     * @fixme check their last-id and check whether we'll need to do a manual pull.
     * @fixme abstract out the fetching so we can work over multiple sites.
     */
    protected function initStreams()
    {
        common_log(LOG_INFO, 'init...');
        // Pull Twitter user IDs for all users we want to pull data for
        $flink = new Foreign_link();
        $flink->service = TWITTER_SERVICE;
        // @fixme probably should do the bitfield check in a whereAdd but it's ugly :D
        $flink->find();

        $userIds = array();
        while ($flink->fetch()) {
            if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
                FOREIGN_NOTICE_RECV) {
                $userIds[] = $flink->foreign_id;

                if (count($userIds) >= self::USERS_PER_STREAM) {
                    $this->spawnStream($userIds);
                    $userIds = array();
                }
            }
        }

        if (count($userIds)) {
            $this->spawnStream($userIds);
        }
    }

    /**
     * Prepare a Site Stream connection for the given chunk of users.
     * The actual connection will be opened later.
     *
     * @param $userIds array of Twitter-side user IDs
     */
    protected function spawnStream($userIds)
    {
        $stream = $this->initSiteStream();
        $stream->followUsers($userIds);

        // Slip the stream reader into our list of active streams.
        // We'll manage its actual connection on the next go-around.
        $this->streams[] = $stream;

        // Record the user->stream mappings; this makes it easier for us to know
        // later if we need to kill something.
        foreach ($userIds as $id) {
            $this->users[$id] = $stream;
        }
    }

    /**
     * Initialize a generic site streams connection object.
     * All our connections will look like this, then we'll add users to them.
     *
     * @return TwitterStreamReader
     */
    protected function initSiteStream()
    {
        $auth = $this->siteStreamAuth();
        $stream = new TwitterSiteStream($auth);

        // Add our event handler callbacks. Whee!
        $this->setupEvents($stream);
        return $stream;
    }

    /**
     * Fetch the Twitter OAuth credentials to use to connect to the Site Streams API.
     *
     * This will use the locally-stored credentials for the applictation's owner account
     * from the site configuration. These should be configured through the administration
     * panels or manually in the config file.
     *
     * Will throw an exception if no credentials can be found -- but beware that invalid
     * credentials won't cause breakage until later.
     *
     * @return TwitterOAuthClient
     */
    protected function siteStreamAuth()
    {
        $token = common_config('twitter', 'stream_token');
        $secret = common_config('twitter', 'stream_secret');
        if (empty($token) || empty($secret)) {
            throw new ServerException('Twitter site streams have not been correctly configured. Configure the app owner account via the admin panel.');
        }
        return new TwitterOAuthClient($token, $secret);
    }

    /**
     * Collect the sockets for all active connections for i/o monitoring.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        $sockets = array();
        foreach ($this->streams as $stream) {
            foreach ($stream->getSockets() as $socket) {
                $sockets[] = $socket;
            }
        }
        return $sockets;
    }

    /**
     * We're ready to process input from one of our data sources! Woooooo!
     * @fixme is there an easier way to map from socket back to owning module? :(
     *
     * @param resource $socket
     * @return boolean success
     */
    public function handleInput($socket)
    {
        foreach ($this->streams as $stream) {
            foreach ($stream->getSockets() as $aSocket) {
                if ($socket === $aSocket) {
                    $stream->handleInput($socket);
                }
            }
        }
        return true;
    }

    /**
     * Start the i/o system up! Prepare our connections and start opening them.
     *
     * @fixme do some rate-limiting on the stream setup
     * @fixme do some sensible backoff on failure etc
     */
    public function start()
    {
        $this->initStreams();
        foreach ($this->streams as $stream) {
            $stream->connect();
        }
        return true;
    }

    /**
     * Close down our connections when the daemon wraps up for business.
     */
    public function finish()
    {
        foreach ($this->streams as $index => $stream) {
            $stream->close();
            unset($this->streams[$index]);
        }
        return true;
    }

    public static function get()
    {
        throw new Exception('not a singleton');
    }

    /**
     * Set up event handlers on the streaming interface.
     *
     * @fixme add more event types as we add handling for them
     */
    protected function setupEvents(TwitterStreamReader $stream)
    {
        $handlers = array(
            'status',
        );
        foreach ($handlers as $event) {
            $stream->hookEvent($event, array($this, 'onTwitter' . ucfirst($event)));
        }
    }

    /**
     * Event callback notifying that a user has a new message in their home timeline.
     * We store the incoming message into the queues for processing, keeping our own
     * daemon running as shiny-fast as possible.
     *
     * @param object $status JSON data: Twitter status update
     * @fixme in all-sites mode we may need to route queue items into another site's
     *        destination queues, or multiple sites.
     */
    protected function onTwitterStatus($status, $context)
    {
        $data = array(
            'status' => $status,
            'for_user' => $context->for_user,
        );
        $qm = QueueManager::get();
        $qm->enqueue($data, 'tweetin');
    }
}


if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$foreground = have_option('f', 'foreground');
$all = have_option('a') || have_option('--all');

$daemon = new TwitterDaemon($id, !$foreground, 1, $all);

$daemon->runOnce();

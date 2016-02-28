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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'fi:at:';
$longoptions = array('id=', 'foreground', 'all', 'threads=');

/**
 * Attempts to get a count of the processors available on the current system
 * to fan out multiple threads.
 *
 * Recognizes Linux and Mac OS X; others will return default of 1.
 *
 * @fixme move this to SpawningDaemon, but to get the default val for help
 *        text we seem to need it before loading infrastructure
 * @return intval
 */
function getProcessorCount()
{
    $cpus = 0;
    switch (PHP_OS) {
    case 'Linux':
        $cpuinfo = file('/proc/cpuinfo');
        foreach (file('/proc/cpuinfo') as $line) {
            if (preg_match('/^processor\s+:\s+(\d+)\s?$/', $line)) {
                $cpus++;
            }
        }
        break;
    case 'Darwin':
        $cpus = intval(shell_exec("/usr/sbin/sysctl -n hw.ncpu 2>/dev/null"));
        break;
    }
    if ($cpus) {
        return $cpus;
    }
    return 1;
}

$threads = getProcessorCount();
$helptext = <<<END_OF_QUEUE_HELP
Daemon script for running queued items.

    -i --id           Identity (default none)
    -f --foreground   Stay in the foreground (default background)
    -a --all          Handle queues for all local sites
                      (requires Stomp queue handler, status_network setup)
    -t --threads=<n>  Spawn <n> processing threads (default $threads)


END_OF_QUEUE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once(INSTALLDIR.'/lib/daemon.php');
require_once(INSTALLDIR.'/classes/Queue_item.php');
require_once(INSTALLDIR.'/classes/Notice.php');

/**
 * Queue handling daemon...
 *
 * The queue daemon by default launches in the background, at which point
 * it'll pass control to the configured QueueManager class to poll for updates.
 *
 * We can then pass individual items through the QueueHandler subclasses
 * they belong to.
 */
class QueueDaemon extends SpawningDaemon
{
    protected $allsites = false;

    function __construct($id=null, $daemonize=true, $threads=1, $allsites=false)
    {
        parent::__construct($id, $daemonize, $threads);
        $this->allsites = $allsites;
    }

    /**
     * Setup and start of run loop for this queue handler as a daemon.
     * Most of the heavy lifting is passed on to the QueueManager's service()
     * method, which passes control on to the QueueHandler's handle()
     * method for each item that comes in on the queue.
     *
     * @return boolean true on success, false on failure
     */
    function runThread()
    {
        $this->log(LOG_INFO, 'checking for queued notices');

        $master = new QueueMaster($this->get_id(), $this->processManager());
        $master->init($this->allsites);
        try {
            $master->service();
        } catch (Exception $e) {
            common_log(LOG_ERR, "Unhandled exception: " . $e->getMessage() . ' ' .
                str_replace("\n", " ", $e->getTraceAsString()));
            return self::EXIT_ERR;
        }

        $this->log(LOG_INFO, 'finished servicing the queue');

        $this->log(LOG_INFO, 'terminating normally');

        return $master->respawn ? self::EXIT_RESTART : self::EXIT_SHUTDOWN;
    }
}

class QueueMaster extends IoMaster
{
    protected $processManager;

    function __construct($id, $processManager)
    {
        parent::__construct($id);
        $this->processManager = $processManager;
    }

    /**
     * Initialize IoManagers which are appropriate to this instance.
     */
    function initManagers()
    {
        $managers = array();
        if (Event::handle('StartQueueDaemonIoManagers', array(&$managers))) {
            $qm = QueueManager::get();
            $qm->setActiveGroup('main');
            $managers[] = $qm;
            $managers[] = $this->processManager;
        }
        Event::handle('EndQueueDaemonIoManagers', array(&$managers));

        foreach ($managers as $manager) {
            $this->instantiate($manager);
        }
    }
}

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

if (have_option('t')) {
    $threads = intval(get_option_value('t'));
} else if (have_option('--threads')) {
    $threads = intval(get_option_value('--threads'));
} else {
    //If there is no argument for number of threads
    //Try reading a config option for the number
    $threads = common_config('queue','threads');
}
if (!$threads) {
    $threads = getProcessorCount();
}

$daemonize = !(have_option('f') || have_option('--foreground'));
$all = have_option('a') || have_option('--all');

$daemon = new QueueDaemon($id, $daemonize, $threads, $all);
$daemon->runOnce();


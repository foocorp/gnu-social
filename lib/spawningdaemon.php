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

/**
 * Base class for daemon that can launch one or more processing threads,
 * respawning them if they exit.
 *
 * This is mainly intended for indefinite workloads such as monitoring
 * a queue or maintaining an IM channel.
 *
 * Child classes should implement the 
 *
 * We can then pass individual items through the QueueHandler subclasses
 * they belong to. We additionally can handle queues for multiple sites.
 *
 * @package QueueHandler
 * @author Brion Vibber <brion@status.net>
 */
abstract class SpawningDaemon extends Daemon
{
    protected $threads=1;

    const EXIT_OK = 0;
    const EXIT_ERR = 1;
    const EXIT_SHUTDOWN = 100;
    const EXIT_RESTART = 101;

    function __construct($id=null, $daemonize=true, $threads=1)
    {
        parent::__construct($daemonize);

        if ($id) {
            $this->set_id($id);
        }
        $this->threads = $threads;
    }

    /**
     * Perform some actual work!
     *
     * @return int exit code; use self::EXIT_SHUTDOWN to request not to respawn.
     */
    public abstract function runThread();

    /**
     * Spawn one or more background processes and let them start running.
     * Each individual process will execute whatever's in the runThread()
     * method, which should be overridden.
     *
     * Child processes will be automatically respawned when they exit.
     *
     * @todo possibly allow for not respawning on "normal" exits...
     *       though ParallelizingDaemon is probably better for workloads
     *       that have forseeable endpoints.
     */
    function run()
    {
        $this->initPipes();

        $children = array();
        for ($i = 1; $i <= $this->threads; $i++) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                $this->log(LOG_ERR, "Couldn't fork for thread $i; aborting\n");
                exit(1);
            } else if ($pid == 0) {
                $this->initAndRunChild($i);
            } else {
                $this->log(LOG_INFO, "Spawned thread $i as pid $pid");
                $children[$i] = $pid;
            }
            sleep(common_config('queue', 'spawndelay'));
        }
        
        $this->log(LOG_INFO, "Waiting for children to complete.");
        while (count($children) > 0) {
            $status = null;
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $i = array_search($pid, $children);
                if ($i === false) {
                    $this->log(LOG_ERR, "Ignoring exit of unrecognized child pid $pid");
                    continue;
                }
                if (pcntl_wifexited($status)) {
                    $exitCode = pcntl_wexitstatus($status);
                    $info = "status $exitCode";
                } else if (pcntl_wifsignaled($status)) {
                    $exitCode = self::EXIT_ERR;
                    $signal = pcntl_wtermsig($status);
                    $info = "signal $signal";
                }
                unset($children[$i]);

                if ($this->shouldRespawn($exitCode)) {
                    $this->log(LOG_INFO, "Thread $i pid $pid exited with $info; respawing.");

                    $pid = pcntl_fork();
                    if ($pid < 0) {
                        $this->log(LOG_ERR, "Couldn't fork to respawn thread $i; aborting thread.\n");
                    } else if ($pid == 0) {
                        $this->initAndRunChild($i);
                    } else {
                        $this->log(LOG_INFO, "Respawned thread $i as pid $pid");
                        $children[$i] = $pid;
                    }
                    sleep(common_config('queue', 'spawndelay'));
                } else {
                    $this->log(LOG_INFO, "Thread $i pid $pid exited with status $exitCode; closing out thread.");
                }
            }
        }
        $this->log(LOG_INFO, "All child processes complete.");
        return true;
    }

    /**
     * Create an IPC socket pair which child processes can use to detect
     * if the parent process has been killed.
     */
    function initPipes()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($sockets) {
            $this->parentWriter = $sockets[0];
            $this->parentReader = $sockets[1];
        } else {
            $this->log(LOG_ERR, "Couldn't create inter-process sockets");
            exit(1);
        }
    }

    /**
     * Build an IOManager that simply ensures that we have a connection
     * to the parent process open. If it breaks, the child process will
     * die.
     *
     * @return ProcessManager
     */
    public function processManager()
    {
        return new ProcessManager($this->parentReader);
    }

    /**
     * Determine whether to respawn an exited subprocess based on its exit code.
     * Otherwise we'll respawn all exits by default.
     *
     * @param int $exitCode
     * @return boolean true to respawn
     */
    protected function shouldRespawn($exitCode)
    {
        if ($exitCode == self::EXIT_SHUTDOWN) {
            // Thread requested a clean shutdown.
            return false;
        } else {
            // Otherwise we should always respawn!
            return true;
        }
    }

    /**
     * Initialize things for a fresh thread, call runThread(), and
     * exit at completion with appropriate return value.
     */
    protected function initAndRunChild($thread)
    {
        // Close the writer end of our parent<->children pipe.
        fclose($this->parentWriter);
        $this->set_id($this->get_id() . "." . $thread);
        $this->resetDb();
        $exitCode = $this->runThread();
        exit($exitCode);
    }

    /**
     * Reconnect to the database for each child process,
     * or they'll get very confused trying to use the
     * same socket.
     */
    protected function resetDb()
    {
        // @fixme do we need to explicitly open the db too
        // or is this implied?
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        // Reconnect main memcached, or threads will stomp on
        // each other and corrupt their requests.
        $cache = Cache::instance();
        if ($cache) {
            $cache->reconnect();
        }

        // Also reconnect memcached for status_network table.
        if (!empty(Status_network::$cache)) {
            Status_network::$cache->close();
            Status_network::$cache = null;
        }
    }

    function log($level, $msg)
    {
        common_log($level, get_class($this) . ' ('. $this->get_id() .'): '.$msg);
    }

    function name()
    {
        return strtolower(get_class($this).'.'.$this->get_id());
    }
}


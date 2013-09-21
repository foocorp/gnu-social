<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstract class for i/o managers
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  QueueManager
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

abstract class IoManager
{
    const SINGLE_ONLY = 0;
    const INSTANCE_PER_SITE = 1;
    const INSTANCE_PER_PROCESS = 2;

    /**
     * Factory function to get an appropriate subclass.
     */
    public static function get() {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Tell the i/o queue master if and how we can handle multi-site
     * processes.
     *
     * Return one of:
     *   IoManager::SINGLE_ONLY
     *   IoManager::INSTANCE_PER_SITE
     *   IoManager::INSTANCE_PER_PROCESS
     */
    public static function multiSite()
    {
        return IoManager::SINGLE_ONLY;
    }
    
    /**
     * If in a multisite configuration, the i/o master will tell
     * your manager about each site you'll have to handle so you
     * can do any necessary per-site setup.
     *
     * The new site will be the currently live configuration during
     * this call.
     */
    public function addSite()
    {
        /* no-op */
    }

    /**
     * This method is called when data is available on one of your
     * i/o manager's sockets. The socket with data is passed in,
     * in case you have multiple sockets.
     *
     * If your i/o manager is based on polling during idle processing,
     * you don't need to implement this.
     *
     * @param resource $socket
     * @return boolean true on success, false on failure
     */
    public function handleInput($socket)
    {
        return true;
    }

    /**
     * Return any open sockets that the run loop should listen
     * for input on. If input comes in on a listed socket,
     * the matching manager's handleInput method will be called.
     *
     * @return array of resources
     */
    function getSockets()
    {
        return array();
    }

    /**
     * Maximum planned time between poll() calls when input isn't waiting.
     * Actual time may vary!
     *
     * When we get a polling hit, the timeout will be cut down to 0 while
     * input is coming in, then will back off to this amount if no further
     * input shows up.
     *
     * By default polling is disabled; you must override this to enable
     * polling for this manager.
     *
     * @return int max poll interval in seconds, or 0 to disable polling
     */
    function pollInterval()
    {
        return 0;
    }

    /**
     * Request a maximum timeout for listeners before the next idle period.
     * Actual wait may be shorter, so don't go crazy in your idle()!
     * Wait could be longer if other handlers performed some slow activity.
     *
     * Return 0 to request that listeners return immediately if there's no
     * i/o and speed up the idle as much as possible; but don't do that all
     * the time as this will burn CPU.
     *
     * @return int seconds
     */
    function timeout()
    {
        return 60;
    }

    /**
     * Called by IoManager after each handled item or empty polling cycle.
     * This is a good time to e.g. service your XMPP connection.
     *
     * Doesn't need to be overridden if there's no maintenance to do.
     */
    function idle()
    {
        return true;
    }

    /**
     * The meat of a polling manager... check for something to do
     * and do it! Note that you should not take too long, as other
     * i/o managers may need to do some work too!
     *
     * On a successful hit, the next poll() call will come as soon
     * as possible followed by exponential backoff up to pollInterval()
     * if no more data is available.
     *
     * @return boolean true if events were hit
     */
    public function poll()
    {
        return false;
    }

    /**
     * Initialization, run when the queue manager starts.
     * If this function indicates failure, the handler run will be aborted.
     *
     * @param IoMaster $master process/event controller
     * @return boolean true on success, false on failure
     */
    public function start($master)
    {
        $this->master = $master;
        return true;
    }

    /**
     * Cleanup, run when the queue manager ends.
     * If this function indicates failure, a warning will be logged.
     *
     * @return boolean true on success, false on failure
     */
    public function finish()
    {
        return true;
    }

    /**
     * Ping iomaster's queue status monitor with a stats update.
     * Only valid during input loop!
     *
     * @param string $counter keyword for counter to increment
     */
    public function stats($counter, $owners=array())
    {
        $this->master->stats($counter, $owners);
    }
}


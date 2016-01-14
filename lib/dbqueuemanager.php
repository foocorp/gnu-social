<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Simple-minded queue manager for storing items in the database
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class DBQueueManager extends QueueManager
{
    /**
     * Saves an object reference into the queue item table.
     * @return boolean true on success
     * @throws ServerException on failure
     */
    public function enqueue($object, $queue)
    {
        $qi = new Queue_item();

        $qi->frame     = $this->encode($object);
        $qi->transport = $queue;
        $qi->created   = common_sql_now();
        $result        = $qi->insert();

        if ($result === false) {
            common_log_db_error($qi, 'INSERT', __FILE__);
            throw new ServerException('DB error inserting queue item');
        }

        $this->stats('enqueued', $queue);

        return true;
    }

    /**
     * Poll every 10 seconds for new events during idle periods.
     * We'll look in more often when there's data available.
     *
     * @return int seconds
     */
    public function pollInterval()
    {
        return 10;
    }

    /**
     * Run a polling cycle during idle processing in the input loop.
     * @return boolean true if we should poll again for more data immediately
     */
    public function poll()
    {
        //$this->_log(LOG_DEBUG, 'Checking for notices...');
        $qi = Queue_item::top($this->activeQueues(), $this->getIgnoredTransports());
        if (!$qi instanceof Queue_item) {
            //$this->_log(LOG_DEBUG, 'No notices waiting; idling.');
            return false;
        }

        try {
            $item = $this->decode($qi->frame);
        } catch (Exception $e) {
            $this->_log(LOG_INFO, "[{$qi->transport}] Discarding: ".$e->getMessage());
            $this->_done($qi);
            return true;
        }

        $rep = $this->logrep($item);
        $this->_log(LOG_DEBUG, "Got {$rep} for transport {$qi->transport}");
        
        $handler = $this->getHandler($qi->transport);
        if ($handler) {
            try {
                $result = $handler->handle($item)
            } catch (Exception $e) {
                $result = false;
                $this->_log(LOG_ERR, "[{$qi->transport}:$rep] Exception thrown: {$e->getMessage()}");
            }
            if ($result) {
                $this->_log(LOG_INFO, "[{$qi->transport}:$rep] Successfully handled item");
                $this->_done($qi);
            } else {
                $this->_log(LOG_INFO, "[{$qi->transport}:$rep] Failed to handle item");
                $this->_fail($qi);
            }
        } else {
            $this->noHandlerFound($qi, $rep);
        }
        return true;
    }

    // What to do if no handler was found. For example, the OpportunisticQM
    // should avoid deleting items just because it can't reach XMPP queues etc.
    protected function noHandlerFound(Queue_item $qi, $rep=null) {
        $this->_log(LOG_INFO, "[{$qi->transport}:{$rep}] No handler for queue {$qi->transport}; discarding.");
        $this->_done($qi);
    }

    /**
     * Delete our claimed item from the queue after successful processing.
     *
     * @param QueueItem $qi
     */
    protected function _done(Queue_item $qi)
    {
        if (empty($qi->claimed)) {
            $this->_log(LOG_WARNING, "Reluctantly releasing unclaimed queue item {$qi->id} from {$qi->transport}");
        }
        $qi->delete();

        $this->stats('handled', $qi->transport);
    }

    /**
     * Free our claimed queue item for later reprocessing in case of
     * temporary failure.
     *
     * @param QueueItem $qi
     */
    protected function _fail(Queue_item $qi, $releaseOnly=false)
    {
        if (empty($qi->claimed)) {
            $this->_log(LOG_WARNING, "[{$qi->transport}:item {$qi->id}] Ignoring failure for unclaimed queue item");
        } else {
            $qi->releaseClaim();
        }

        if (!$releaseOnly) {
            $this->stats('error', $qi->transport);
        }
    }
}

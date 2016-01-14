<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * A queue manager interface for just doing things immediately
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class UnQueueManager extends QueueManager
{

    /**
     * Dummy queue storage manager: instead of saving events for later,
     * we just process them immediately. This is only suitable for events
     * that can be processed quickly and don't need polling or long-running
     * connections to another server such as XMPP.
     *
     * @param Notice $object    this specific manager just handles Notice objects anyway
     * @param string $queue
     */
    function enqueue($object, $transport)
    {
        try {
            $handler = $this->getHandler($transport);
            $handler->handle($object);
        } catch (NoQueueHandlerException $e) {
            if (Event::handle('UnqueueHandleNotice', array(&$object, $transport))) {
                throw new ServerException("UnQueueManager: Unknown queue transport: $transport");
            }
        }
    }
}

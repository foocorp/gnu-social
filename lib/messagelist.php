<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * The message list widget
 *
 * PHP version 5
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
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Message list widget
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class MessageList extends Widget
{
    var $message;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     Output context
     * @param Message       $message Stream of messages to show
     */
    function __construct($out, $message)
    {
        parent::__construct($out);
        $this->message = $message;
    }

    /**
     * Show the widget
     *
     * Uses newItem() to create each new item.
     *
     * @return integer count of messages seen.
     */
    function show()
    {
            $cnt = 0;

            $this->out->elementStart('div', array('id' =>'notices_primary'));

            // TRANS: Header in message list.
            $this->out->element('h2', null, _('Messages'));

            $this->out->elementStart('ul', 'notices messages');

            while ($this->message->fetch() && $cnt <= MESSAGES_PER_PAGE) {

                $cnt++;

                if ($cnt > MESSAGES_PER_PAGE) {
                    break;
                }

                $mli = $this->newItem($this->message);

                $mli->show();
            }

            $this->out->elementEnd('ul');

            $this->out->elementEnd('div');
    }

    /**
     * Create a new message item for a message
     *
     * @param Message $message The message to show
     *
     * @return MessageListItem an item to show
     */
    abstract function newItem($message);
}

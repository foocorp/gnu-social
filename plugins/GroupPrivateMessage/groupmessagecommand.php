<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Command object for messages to groups
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
 * @category  Command
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
 * Command object for messages to groups
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupMessageCommand extends Command
{
    /** User sending the message. */
    var $user;
    /** Nickname of the group they're sending to. */
    var $nickname;
    /** Text of the message. */
    var $text;

    /**
     * Constructor
     *
     * @param User   $user     User sending the message
     * @param string $nickname Nickname of the group
     * @param string $text     Text of message
     */
    function __construct($user, $nickname, $text)
    {
        $this->user     = $user;
        $this->nickname = $nickname;
        $this->text     = $text;
    }

    function handle($channel)
    {
        // Throws a command exception if group not found
        $group = $this->getGroup($this->nickname);

        $gm = Group_message::send($this->user, $group, $this->text);

        $channel->output($this->user,
                         // TRANS: Succes message after sending private group message to group %s.
                         sprintf(_m('Direct message to group %s sent.'),
                                 $group->nickname));

        return true;
    }
}

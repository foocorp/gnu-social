<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a single message
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Show a single message
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ShowmessageAction extends Action
{
    /**
     * Message object to show
     */
    var $message = null;

    /**
     * The current user
     */

    var $user = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->page = 1;

        $id            = $this->trimmed('message');
        $this->message = Message::getKV('id', $id);

        if (!$this->message) {
            // TRANS: Client error displayed requesting a single message that does not exist.
            $this->clientError(_('No such message.'), 404);
            return false;
        }

        $this->user = common_current_user();

        if (empty($this->user) ||
            ($this->user->id != $this->message->from_profile &&
             $this->user->id != $this->message->to_profile)) {
            // TRANS: Client error displayed requesting a single direct message the requesting user was not a party in.
            throw new ClientException(_('Only the sender and recipient ' .
                                        'may read this message.'), 403);
        }

        return true;
    }

    function handle($args)
    {
        $this->showPage();
    }

    function title()
    {
        if ($this->user->id == $this->message->from_profile) {
            $to = $this->message->getTo();
            // @todo FIXME: Might be nice if the timestamp could be localised.
            // TRANS: Page title for single direct message display when viewing user is the sender.
            // TRANS: %1$s is the addressed user's nickname, $2$s is a timestamp.
            return sprintf(_('Message to %1$s on %2$s'),
                             $to->nickname,
                             common_exact_date($this->message->created));
        } else if ($this->user->id == $this->message->to_profile) {
            $from = $this->message->getFrom();
            // @todo FIXME: Might be nice if the timestamp could be localised.
            // TRANS: Page title for single message display.
            // TRANS: %1$s is the sending user's nickname, $2$s is a timestamp.
            return sprintf(_('Message from %1$s on %2$s'),
                             $from->nickname,
                             common_exact_date($this->message->created));
        }
    }


    function showContent()
    {
        $this->elementStart('ul', 'notices messages');
        $ml = new ShowMessageListItem($this, $this->message, $this->user);
        $ml->show();
        $this->elementEnd('ul');
    }

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Don't show aside
     *
     * @return void
     */

    function showAside() {
    }
}

class ShowMessageListItem extends MessageListItem
{
    var $user;

    function __construct($out, $message, $user)
    {
        parent::__construct($out, $message);
        $this->user = $user;
    }

    function getMessageProfile()
    {
        if ($this->user->id == $this->message->from_profile) {
            return $this->message->getTo();
        } else if ($this->user->id == $this->message->to_profile) {
            return $this->message->getFrom();
        } else {
            // This shouldn't happen
            return null;
        }
    }
}

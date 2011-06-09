<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * common superclass for direct messages inbox and outbox
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
 * @category  Message
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * common superclass for direct messages inbox and outbox
 *
 * @category Message
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      InboxAction
 * @see      OutboxAction
 */
class MailboxAction extends Action
{
    var $page = null;

    function prepare($args)
    {
        parent::prepare($args);

        $nickname   = common_canonical_nickname($this->arg('nickname'));
        $this->user = User::staticGet('nickname', $nickname);
        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * output page based on arguments
     *
     * @param array $args HTTP arguments (from $_REQUEST)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if (!$this->user) {
            // TRANS: Client error displayed when trying to access a mailbox without providing a user.
            $this->clientError(_('No such user.'), 404);
            return;
        }

        $cur = common_current_user();

        if (!$cur || $cur->id != $this->user->id) {
            // TRANS: Client error displayed when trying to access a mailbox that is not of the logged in user.
            $this->clientError(_('Only the user can read their own mailboxes.'),
                403);
            return;
        }

        $this->showPage();
    }

    function showNoticeForm()
    {
        $message_form = new MessageForm($this);
        $message_form->show();
    }

    function showContent()
    {
        $message = $this->getMessages();

        if ($message) {

            $ml = $this->getMessageList($message);

            $cnt = $ml->show();

            $this->pagination($this->page > 1,
                              $cnt > MESSAGES_PER_PAGE,
                              $this->page,
                              $this->trimmed('action'),
                              array('nickname' => $this->user->nickname));
        } else {
            $this->element('p',
                           'guide',
                           // TRANS: Message displayed when there are no private messages in the inbox of a user.
                           _('You have no private messages. '.
                             'You can send private message to engage other users in conversation. '.
                             'People can send you messages for your eyes only.'));
        }
    }

    function getMessages()
    {
        return null;
    }

    function getMessageList($message)
    {
        return null;
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    /**
     * Mailbox actions are read only
     *
     * @param array $args other arguments
     *
     * @return boolean
     */
    function isReadOnly($args)
    {
         return true;
    }

    function showObjectNav()
    {
        $mm = new MailboxMenu($this);
        $mm->show();
    }
}

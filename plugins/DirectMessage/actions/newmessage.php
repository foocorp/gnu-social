<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Handler for posting new messages
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
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Action for posting new direct messages
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class NewmessageAction extends FormAction
{
    var $content = null;
    var $to = null;
    var $other = null;

    protected $form = 'Message';    // will become MessageForm later

    /**
     * Title of the page
     *
     * Note that this usually doesn't get called unless something went wrong
     *
     * @return string page title
     */

    function title()
    {
        // TRANS: Page title for new direct message page.
        return _('New message');
    }

    protected function doPreparation()
    {
        $this->content = $this->trimmed('content');
        $this->to = $this->trimmed('to');

        if ($this->to) {

            $this->other = Profile::getKV('id', $this->to);

            if (!$this->other instanceof Profile) {
                // TRANS: Client error displayed trying to send a direct message to a non-existing user.
                $this->clientError(_('No such user.'), 404);
            }

            if (!$this->other->isLocal()) {
                // TRANS: Explains that current federation does not support direct, private messages yet.
                $this->clientError(_('You cannot send direct messages to federated users yet.'));
            }

            if (!$this->scoped->mutuallySubscribed($this->other)) {
                // TRANS: Client error displayed trying to send a direct message to a user while sender and
                // TRANS: receiver are not subscribed to each other.
                $this->clientError(_('You cannot send a message to this user.'), 404);
            }
        }

        return true;
    }

    protected function doPost()
    {
        assert($this->scoped instanceof Profile); // XXX: maybe an error instead...

        if (empty($this->content)) {
            // TRANS: Form validator error displayed trying to send a direct message without content.
            $this->clientError(_('No content!'));
        }

        $content_shortened = $this->scoped->shortenLinks($this->content);

        if (Message::contentTooLong($content_shortened)) {
            // TRANS: Form validation error displayed when message content is too long.
            // TRANS: %d is the maximum number of characters for a message.
            $this->clientError(sprintf(_m('That\'s too long. Maximum message size is %d character.',
                                       'That\'s too long. Maximum message size is %d characters.',
                                       Message::maxContent()),
                                    Message::maxContent()));
        }

        if (!$this->other instanceof Profile) {
            // TRANS: Form validation error displayed trying to send a direct message without specifying a recipient.
            $this->clientError(_('No recipient specified.'));
        } else if (!$this->scoped->mutuallySubscribed($this->other)) {
            // TRANS: Client error displayed trying to send a direct message to a user while sender and
            // TRANS: receiver are not subscribed to each other.
            $this->clientError(_('You cannot send a message to this user.'), 404);
        } else if ($this->scoped->id == $this->other->id) {
            // TRANS: Client error displayed trying to send a direct message to self.
            $this->clientError(_('Do not send a message to yourself; ' .
                'just say it to yourself quietly instead.'), 403);
        }

        $message = Message::saveNew($this->scoped->id, $this->other->id, $this->content, 'web');
        $message->notify();

        if (GNUsocial::isAjax()) {
            // TRANS: Confirmation text after sending a direct message.
            // TRANS: %s is the direct message recipient.
            return sprintf(_('Direct message to %s sent.'), $this->other->getNickname());
        }

        $url = common_local_url('outbox', array('nickname' => $this->scoped->getNickname()));
        common_redirect($url, 303);
    }

    function showNoticeForm()
    {
        // Just don't show a NoticeForm
    }
}

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

if (!defined('STATUSNET')) {
    exit(1);
}

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

    /**
     * Handle input, produce output
     *
     * @param array $args $_REQUEST contents
     *
     * @return void
     */

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $user = $this->scoped->getUser();

        $this->content = $this->trimmed('content');
        $this->to = $this->trimmed('to');

        if ($this->to) {

            $this->other = User::getKV('id', $this->to);

            if (!$this->other) {
                // TRANS: Client error displayed trying to send a direct message to a non-existing user.
                $this->clientError(_('No such user.'), 404);
            }

            if (!$user->mutuallySubscribed($this->other)) {
                // TRANS: Client error displayed trying to send a direct message to a user while sender and
                // TRANS: receiver are not subscribed to each other.
                $this->clientError(_('You cannot send a message to this user.'), 404);
            }
        }

        return true;
    }

    protected function handlePost()
    {
        parent::handlePost();

        assert($this->scoped); // XXX: maybe an error instead...
        $user = $this->scoped->getUser();

        if (!$this->content) {
            // TRANS: Form validator error displayed trying to send a direct message without content.
            $this->clientError(_('No content!'));
        } else {
            $content_shortened = $user->shortenLinks($this->content);

            if (Message::contentTooLong($content_shortened)) {
                // TRANS: Form validation error displayed when message content is too long.
                // TRANS: %d is the maximum number of characters for a message.
                $this->clientError(sprintf(_m('That\'s too long. Maximum message size is %d character.',
                                           'That\'s too long. Maximum message size is %d characters.',
                                           Message::maxContent()),
                                        Message::maxContent()));
            }
        }

        if (!$this->other) {
            // TRANS: Form validation error displayed trying to send a direct message without specifying a recipient.
            $this->clientError(_('No recipient specified.'));
        } else if (!$user->mutuallySubscribed($this->other)) {
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

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a direct message.
            $this->element('title', null, _('Message sent'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', array('id' => 'command_result'),
                // TRANS: Confirmation text after sending a direct message.
                // TRANS: %s is the direct message recipient.
                sprintf(_('Direct message to %s sent.'),
                    $this->other->nickname));
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            $url = common_local_url('outbox',
                array('nickname' => $this->scoped->nickname));
            common_redirect($url, 303);
        }
    }

    /**
     * Show an Ajax-y error message
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */

    function ajaxErrorMsg($msg)
    {
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        // TRANS: Page title after an AJAX error occurred on the "send direct message" page.
        $this->element('title', null, _('Ajax Error'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->element('p', array('id' => 'error'), $msg);
        $this->elementEnd('body');
        $this->endHTML();
    }

    function showForm($msg = null)
    {
        if ($msg && $this->boolean('ajax')) {
            $this->ajaxErrorMsg($msg);
            return;
        }

        $this->msg = $msg;
        if ($this->trimmed('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title on page for sending a direct message.
            $this->element('title', null, _('New message'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNoticeForm();
            $this->elementEnd('body');
            $this->endHTML();
        }
        else {
            $this->showPage();
        }
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        }
    }

    // Do nothing (override)

    function showNoticeForm()
    {
        $message_form = new MessageForm($this, $this->other, $this->content);
        $message_form->show();
    }
}

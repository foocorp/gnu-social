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

define('MESSAGES_PER_PAGE', 20);

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

class MailboxAction extends CurrentUserDesignAction
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
            $this->clientError(_('No such user.'), 404);
            return;
        }

        $cur = common_current_user();

        if (!$cur || $cur->id != $this->user->id) {
            $this->clientError(_('Only the user can read their own mailboxes.'),
                403);
            return;
        }

        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
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
            $cnt = 0;
            $this->elementStart('div', array('id' =>'notices_primary'));
            $this->element('h2', null, _('Notices'));
            $this->elementStart('ul', 'notices');

            while ($message->fetch() && $cnt <= MESSAGES_PER_PAGE) {
                $cnt++;

                if ($cnt > MESSAGES_PER_PAGE) {
                    break;
                }

                $this->showMessage($message);
            }

            $this->elementEnd('ul');

            $this->pagination($this->page > 1, $cnt > MESSAGES_PER_PAGE,
                              $this->page, $this->trimmed('action'),
                              array('nickname' => $this->user->nickname));
            $this->elementEnd('div');
            $message->free();
            unset($message);
        }
        else {
            $this->element('p', 'guide', _('You have no private messages. You can send private message to engage other users in conversation. People can send you messages for your eyes only.'));
        }
    }

    function getMessages()
    {
        return null;
    }

    /**
     * returns the profile we want to show with the message
     *
     * For inboxes, we show the sender; for outboxes, the recipient.
     *
     * @param Message $message The message to get the profile for
     *
     * @return Profile The profile that matches the message
     */

    function getMessageProfile($message)
    {
        return null;
    }

    /**
     * show a single message in the list format
     *
     * XXX: This needs to be extracted out into a MessageList similar
     * to NoticeList.
     *
     * @param Message $message the message to show
     *
     * @return void
     */

    function showMessage($message)
    {
        $this->elementStart('li', array('class' => 'hentry notice',
                                         'id' => 'message-' . $message->id));

        $profile = $this->getMessageProfile($message);

        $this->elementStart('div', 'entry-title');
        $this->elementStart('span', 'vcard author');
        $this->elementStart('a', array('href' => $profile->profileurl,
                                       'class' => 'url'));
        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        $this->element('img', array('src' => ($avatar) ?
                                    $avatar->displayUrl() :
                                    Avatar::defaultImage(AVATAR_STREAM_SIZE),
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($profile->fullname) ? $profile->fullname :
                                    $profile->nickname));
        $this->element('span', array('class' => 'nickname fn'),
                            $profile->nickname);
        $this->elementEnd('a');
        $this->elementEnd('span');

        // FIXME: URL, image, video, audio
        $this->elementStart('p', array('class' => 'entry-content'));
        $this->raw($message->rendered);
        $this->elementEnd('p');
        $this->elementEnd('div');

        $messageurl = common_local_url('showmessage',
                                       array('message' => $message->id));

        // XXX: we need to figure this out better. Is this right?
        if (strcmp($message->uri, $messageurl) != 0 &&
            preg_match('/^http/', $message->uri)) {
            $messageurl = $message->uri;
        }

        $this->elementStart('div', 'entry-content');
        $this->elementStart('a', array('rel' => 'bookmark',
                                       'class' => 'timestamp',
                                       'href' => $messageurl));
        $dt = common_date_iso8601($message->created);
        $this->element('abbr', array('class' => 'published',
                                     'title' => $dt),
                               common_date_string($message->created));
        $this->elementEnd('a');

        if ($message->source) {
            $this->elementStart('span', 'source');
            // FIXME: bad i18n. Device should be a parameter (from %s).
            $this->text(_('from'));
            $this->element('span', 'device', $this->showSource($message->source));
            $this->elementEnd('span');
        }
        $this->elementEnd('div');

        $this->elementEnd('li');
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
     * Show the source of the message
     *
     * Returns either the name (and link) of the API client that posted the notice,
     * or one of other other channels.
     *
     * @param string $source the source of the message
     *
     * @return void
     */

    function showSource($source)
    {
        $source_name = _($source);
        switch ($source) {
        case 'web':
        case 'xmpp':
        case 'mail':
        case 'omb':
        case 'api':
            $this->element('span', 'device', $source_name);
            break;
        default:
            $ns = Notice_source::staticGet($source);
            if ($ns) {
                $this->elementStart('span', 'device');
                $this->element('a', array('href' => $ns->url,
                                               'rel' => 'external'),
                                    $ns->name);
                $this->elementEnd('span');
            } else {
                $this->element('span', 'device', $source_name);
            }
            break;
        }
        return;
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

}

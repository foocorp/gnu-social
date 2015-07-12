<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of replies
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
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * List of replies
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RepliesAction extends ShowstreamAction
{
    public function getStream()
    {
        return new ReplyNoticeStream($this->target->getID(), $this->scoped);
    }

    /**
     * Title of the page
     *
     * Includes name of user and page number.
     *
     * @return string title of page
     */
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title for first page of replies for a user.
            // TRANS: %s is a user nickname.
            return sprintf(_("Replies to %s"), $this->target->getNickname());
        } else {
            // TRANS: Title for all but the first page of replies for a user.
            // TRANS: %1$s is a user nickname, %2$d is a page number.
            return sprintf(_('Replies to %1$s, page %2$d'),
                           $this->target->getNickname(),
                           $this->page);
        }
    }

    /**
     * Feeds for the <head> section
     *
     * @return void
     */
    function getFeeds()
    {
        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelineMentions',
                                               array(
                                                    'id' => $this->target->getNickname(),
                                                    'format' => 'as')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (Activity Streams JSON)'),
                                      $this->target->getNickname())),
                     new Feed(Feed::RSS1,
                              common_local_url('repliesrss',
                                               array('nickname' => $this->target->getNickname())),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (RSS 1.0)'),
                                      $this->target->getNickname())),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineMentions',
                                               array(
                                                    'id' => $this->target->getNickname(),
                                                    'format' => 'rss')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (RSS 2.0)'),
                                      $this->target->getNickname())),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineMentions',
                                               array(
                                                    'id' => $this->target->getNickname(),
                                                    'format' => 'atom')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (Atom)'),
                                    $this->target->getNickname())));
    }

    function showContent()
    {
        $nl = new PrimaryNoticeList($this->notice, $this, array('show_n'=>NOTICES_PER_PAGE));

        $cnt = $nl->show();
        if (0 === $cnt) {
            $this->showEmptyListMessage();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'replies',
                          array('nickname' => $this->target->getNickname()));
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message for page with replies for a user.
        // TRANS: %1$s is the user nickname.
        $message = sprintf(_('This is the timeline showing replies to %1$s but no notices have arrived yet.'), $this->target->getNickname());
        $message .= ' ';    // Spacing between this sentence and the next.

        if (common_logged_in()) {
            if ($this->target->getID() === $this->scoped->getID()) {
                // TRANS: Empty list message for page with replies for a user for the logged in user.
                // TRANS: This message contains a Markdown link in the form [link text](link).
                $message .= _('You can engage other users in a conversation, subscribe to more people or [join groups](%%action.groups%%).');
            } else {
                // TRANS: Empty list message for page with replies for a user for all logged in users but the user themselves.
                // TRANS: %1$s is a user nickname and %2$s is the same but with a prepended '@' character. This message contains a Markdown link in the form [link text](link).
                $message .= sprintf(_('You can try to [nudge %1$s](../%1$s) or [post something to them](%%%%action.newnotice%%%%?content=%2$s).'), $this->target->getNickname(), '@' . $this->target->getNickname());
            }
        } else {
            // TRANS: Empty list message for page with replies for a user for not logged in users.
            // TRANS: %1$s is a user nickname. This message contains a Markdown link in the form [link text](link).
            $message .= sprintf(_('Why not [register an account](%%%%action.register%%%%) and then nudge %s or post a notice to them.'), $this->target->getNickname());
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    public function isReadOnly($args)
    {
        return true;
    }
}

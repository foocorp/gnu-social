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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

/**
 * List of replies
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RepliesAction extends Action
{
    var $page = null;
    var $notice;

    /**
     * Prepare the object
     *
     * Check the input values and initialize the object.
     * Shows an error page on bad input.
     *
     * @param array $args $_REQUEST data
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $nickname = common_canonical_nickname($this->arg('nickname'));

        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when trying to reply to a non-exsting user.
            $this->clientError(_('No such user.'));
            return false;
        }

        $profile = $this->user->getProfile();

        if (!$profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_set_returnto($this->selfUrl());

        $stream = new ReplyNoticeStream($this->user->id,
                                        Profile::current());

        $this->notice = $stream->getNotices(($this->page-1) * NOTICES_PER_PAGE,
                                            NOTICES_PER_PAGE + 1);

        if($this->page > 1 && $this->notice->N == 0){
            // TRANS: Server error when page not found (404)
            $this->serverError(_('No such page.'),$code=404);
        }

        return true;
    }

    /**
     * Handle a request
     *
     * Just show the page. All args already handled.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
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
            return sprintf(_("Replies to %s"), $this->user->nickname);
        } else {
            // TRANS: Title for all but the first page of replies for a user.
            // TRANS: %1$s is a user nickname, %2$d is a page number.
            return sprintf(_('Replies to %1$s, page %2$d'),
                           $this->user->nickname,
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
                                                    'id' => $this->user->nickname,
                                                    'format' => 'as')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (Activity Streams JSON)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS1,
                              common_local_url('repliesrss',
                                               array('nickname' => $this->user->nickname)),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (RSS 1.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineMentions',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'rss')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (RSS 2.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineMentions',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'atom')),
                              // TRANS: Link for feed with replies for a user.
                              // TRANS: %s is a user nickname.
                              sprintf(_('Replies feed for %s (Atom)'),
                                    $this->user->nickname)));
    }

    /**
     * Show the content
     *
     * A list of notices that are replies to the user, plus pagination.
     *
     * @return void
     */
    function showContent()
    {
        $nl = new NoticeList($this->notice, $this);

        $cnt = $nl->show();
        if (0 === $cnt) {
            $this->showEmptyListMessage();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'replies',
                          array('nickname' => $this->user->nickname));
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message for page with replies for a user.
        // TRANS: %1$s and %s$s are the user nickname.
        $message = sprintf(_('This is the timeline showing replies to %1$s but %2$s hasn\'t received a notice to them yet.'),
                           $this->user->nickname,
                           $this->user->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                // TRANS: Empty list message for page with replies for a user for the logged in user.
                // TRANS: This message contains a Markdown link in the form [link text](link).
                $message .= _('You can engage other users in a conversation, subscribe to more people or [join groups](%%action.groups%%).');
            } else {
                // TRANS: Empty list message for page with replies for a user for all logged in users but the user themselves.
                // TRANS: %1$s, %2$s and %3$s are a user nickname. This message contains a Markdown link in the form [link text](link).
                $message .= sprintf(_('You can try to [nudge %1$s](../%2$s) or [post something to them](%%%%action.newnotice%%%%?status_textarea=%3$s).'), $this->user->nickname, $this->user->nickname, '@' . $this->user->nickname);
            }
        }
        else {
            // TRANS: Empty list message for page with replies for a user for not logged in users.
            // TRANS: %1$s is a user nickname. This message contains a Markdown link in the form [link text](link).
            $message .= sprintf(_('Why not [register an account](%%%%action.register%%%%) and then nudge %s or post a notice to them.'), $this->user->nickname);
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function isReadOnly($args)
    {
        return true;
    }
}

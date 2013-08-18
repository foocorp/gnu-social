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
class ShowfavoritesAction extends Action
{
    /** User we're getting the faves of */
    var $user = null;
    /** Page of the faves we're on */
    var $page = null;

    /**
     * Is this a read-only page?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
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
            // TRANS: Title for first page of favourite notices of a user.
            // TRANS: %s is the user for whom the favourite notices are displayed.
            return sprintf(_('%s\'s favorite notices'), $this->user->nickname);
        } else {
            // TRANS: Title for all but the first page of favourite notices of a user.
            // TRANS: %1$s is the user for whom the favourite notices are displayed, %2$d is the page number.
            return sprintf(_('%1$s\'s favorite notices, page %2$d'),
                           $this->user->nickname,
                           $this->page);
        }
    }

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
            // TRANS: Client error displayed when trying to display favourite notices for a non-existing user.
            $this->clientError(_('No such user.'));
            return false;
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        common_set_returnto($this->selfUrl());

        $cur = common_current_user();

        if (!empty($cur) && $cur->id == $this->user->id) {

            // Show imported/gateway notices as well as local if
            // the user is looking at their own favorites

            $this->notice = $this->user->favoriteNotices(true, ($this->page-1)*NOTICES_PER_PAGE,
                                                   NOTICES_PER_PAGE + 1);
        } else {
            $this->notice = $this->user->favoriteNotices(false, ($this->page-1)*NOTICES_PER_PAGE,
                                                   NOTICES_PER_PAGE + 1);
        }

        if (empty($this->notice)) {
            // TRANS: Server error displayed when favourite notices could not be retrieved from the database.
            $this->serverError(_('Could not retrieve favorite notices.'));
            return;
        }

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
     * Feeds for the <head> section
     *
     * @return array Feed objects to show
     */
    function getFeeds()
    {
        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelineFavorites',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'as')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (Activity Streams JSON)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS1,
                              common_local_url('favoritesrss',
                                               array('nickname' => $this->user->nickname)),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (RSS 1.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineFavorites',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'rss')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (RSS 2.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineFavorites',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'atom')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (Atom)'),
                                      $this->user->nickname)));
    }

    function showEmptyListMessage()
    {
        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                // TRANS: Text displayed instead of favourite notices for the current logged in user that has no favourites.
                $message = _('You haven\'t chosen any favorite notices yet. Click the fave button on notices you like to bookmark them for later or shed a spotlight on them.');
            } else {
                // TRANS: Text displayed instead of favourite notices for a user that has no favourites while logged in.
                // TRANS: %s is a username.
                $message = sprintf(_('%s hasn\'t added any favorite notices yet. Post something interesting they would add to their favorites :)'), $this->user->nickname);
            }
        }
        else {
                // TRANS: Text displayed instead of favourite notices for a user that has no favourites while not logged in.
                // TRANS: %s is a username, %%%%action.register%%%% is a link to the user registration page.
                // TRANS: (link text)[link] is a Mark Down link.
            $message = sprintf(_('%s hasn\'t added any favorite notices yet. Why not [register an account](%%%%action.register%%%%) and then post something interesting they would add to their favorites :)'), $this->user->nickname);
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Show the content
     *
     * A list of notices that this user has marked as a favorite
     *
     * @return void
     */
    function showContent()
    {
        $nl = new FavoritesNoticeList($this->notice, $this);

        $cnt = $nl->show();
        if (0 == $cnt) {
            $this->showEmptyListMessage();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'showfavorites',
                          array('nickname' => $this->user->nickname));
    }

    function showPageNotice() {
        // TRANS: Page notice for show favourites page.
        $this->element('p', 'instructions', _('This is a way to share what you like.'));
    }
}

class FavoritesNoticeList extends NoticeList
{
    function newListItem($notice)
    {
        return new FavoritesNoticeListItem($notice, $this->out);
    }
}

// All handled by superclass
class FavoritesNoticeListItem extends DoFollowListItem
{
}

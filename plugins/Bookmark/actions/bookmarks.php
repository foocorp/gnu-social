<?php
/**
 * Give a warm greeting to our friendly user
 *
 * PHP version 5
 *
 * @category Bookmark
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * List currently logged-in user's bookmakrs
 *
 * @category Bookmark
 * @package  StatusNet
 * @author   Stephane Berube <chimo@chromic.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     https://github.com/chimo/BookmarkList
 */
class BookmarksAction extends Action
{
    var $user = null;
    var $gc   = null;

    /**
     * Take arguments for running
     *
     * This method is called first, and it lets the action class get
     * all its arguments and validate them. It's also the time
     * to fetch any relevant data from the database.
     *
     * Action classes should run parent::prepare($args) as the first
     * line of this method to make sure the default argument-processing
     * happens.
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (common_config('singleuser', 'enabled')) {
            $nickname = User::singleUserNickname();
        } else {
            // PHP 5.4
            // $nickname = $this->returnToArgs()[1]['nickname'];

            // PHP < 5.4
            $nickname = $this->returnToArgs();
            $nickname = $nickname[1]['nickname'];
        }
        
        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when trying to display bookmarks for a non-existing user.
            $this->clientError(_('No such user.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $stream = new BookmarksNoticeStream($this->user->id, true);
        $this->notices = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                                NOTICES_PER_PAGE + 1); 

        if($this->page > 1 && $this->notices->N == 0) {
            throw new ClientException(_('No such page.'), 404);
        }

        return true;
    }

    /**
     * Handle request
     *
     * This is the main method for handling a request. Note that
     * most preparation should be done in the prepare() method;
     * by the time handle() is called the action should be
     * more or less ready to go.
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Title of this page
     *
     * Override this method to show a custom title.
     *
     * @return string Title of the page
     */
    function title()
    {

        if (empty($this->user)) {
            // TRANS: Page title for sample plugin.
            return _m('Log in');
        } else {
            // TRANS: Page title for sample plugin. %s is a user nickname.
            return sprintf(_m('%s\'s bookmarks'), $this->user->nickname);
        }
    }

    /**
     * Feeds for the <head> section
     *
     * @return array Feed objects to show
     */
    function getFeeds()
    {
        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelineBookmarks',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'as')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (Activity Streams JSON)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS1,
                              common_local_url('bookmarksrss',
                                               array('nickname' => $this->user->nickname)),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (RSS 1.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineBookmarks',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'rss')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (RSS 2.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineBookmarks',
                                               array(
                                                    'id' => $this->user->nickname,
                                                    'format' => 'atom')),
                              // TRANS: Feed link text. %s is a username.
                              sprintf(_('Feed for favorites of %s (Atom)'),
                                      $this->user->nickname)));
    }

    /**
     * Show content in the content area
     *
     * The default StatusNet page has a lot of decorations: menus,
     * logos, tabs, all that jazz. This method is used to show
     * content in the content area of the page; it's the main
     * thing you want to overload.
     *
     * This method also demonstrates use of a plural localized string.
     *
     * @return void
     */
    function showContent()
    {

        $nl = new NoticeList($this->notices, $this);

        $cnt = $nl->show();

        if ($cnt == 0) {
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1,
                $cnt > NOTICES_PER_PAGE,
                $this->page, 'bookmarks',
                array('nickname' => $this->user->nickname));
    }

    function showEmptyList() {
        $message = sprintf(_('This is %1$s\'s bookmark stream, but %1$s hasn\'t bookmarked anything yet.'), $this->user->nickname) . ' ';

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Return true if read only.
     *
     * Some actions only read from the database; others read and write.
     * The simple database load-balancer built into StatusNet will
     * direct read-only actions to database mirrors (if they are configured),
     * and read-write actions to the master database.
     *
     * This defaults to false to avoid data integrity issues, but you
     * should make sure to overload it for performance gains.
     *
     * @param array $args other arguments, if RO/RW status depends on them.
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}

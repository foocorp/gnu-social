<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * @category Actions
 * @package  Actions
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brion Vibber <brion@pobox.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Meitar Moscovitz <meitarm@gmail.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <millette@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Siebrand Mazeland <s.mazeland@xs4all.nl>
 * @author   Zach Copley <zach@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @link     http://status.net
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

class AllAction extends ProfileAction
{
    var $notice;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $cur = common_current_user();

        if (!empty($cur) && $cur->id == $this->user->id) {
            $this->notice = $this->user->noticeInbox(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        } else {
            $this->notice = $this->user->noticesWithFriends(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        }

        if ($this->page > 1 && $this->notice->N == 0) {
            // TRANS: Server error when page not found (404).
            $this->serverError(_('No such page.'), $code = 404);
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!$this->user) {
            // TRANS: Client error when user not found for an action.
            $this->clientError(_('No such user.'));
            return;
        }

        $this->showPage();
    }

    function title()
    {
        if ($this->page > 1) {
            // TRANS: Page title. %1$s is user nickname, %2$d is page number
            return sprintf(_('%1$s and friends, page %2$d'), $this->user->nickname, $this->page);
        } else {
            // TRANS: Page title. %s is user nickname
            return sprintf(_("%s and friends"), $this->user->nickname);
        }
    }

    function getFeeds()
    {
        return array(
            new Feed(Feed::RSS1,
                common_local_url(
                    'allrss', array(
                        'nickname' =>
                        $this->user->nickname)
                ),
                // TRANS: %s is user nickname.
                sprintf(_('Feed for friends of %s (RSS 1.0)'), $this->user->nickname)),
            new Feed(Feed::RSS2,
                common_local_url(
                    'ApiTimelineFriends', array(
                        'format' => 'rss',
                        'id' => $this->user->nickname
                    )
                ),
                // TRANS: %s is user nickname.
                sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->user->nickname)),
            new Feed(Feed::ATOM,
                common_local_url(
                    'ApiTimelineFriends', array(
                        'format' => 'atom',
                        'id' => $this->user->nickname
                    )
                ),
                // TRANS: %s is user nickname.
                sprintf(_('Feed for friends of %s (Atom)'), $this->user->nickname))
        );
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message. %s is a user nickname.
        $message = sprintf(_('This is the timeline for %s and friends but no one has posted anything yet.'), $this->user->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                // TRANS: Encouragement displayed on logged in user's empty timeline.
                // TRANS: This message contains Markdown links. Keep "](" together.
                $message .= _('Try subscribing to more people, [join a group](%%action.groups%%) or post something yourself.');
            } else {
                // TRANS: %1$s is user nickname, %2$s is user nickname, %2$s is user nickname prefixed with "@".
                // TRANS: This message contains Markdown links. Keep "](" together.
                $message .= sprintf(_('You can try to [nudge %1$s](../%2$s) from their profile or [post something to them](%%%%action.newnotice%%%%?status_textarea=%3$s).'), $this->user->nickname, $this->user->nickname, '@' . $this->user->nickname);
            }
        } else {
            // TRANS: Encoutagement displayed on empty timeline user pages for anonymous users.
            // TRANS: %s is a user nickname. This message contains Markdown links. Keep "](" together.
            $message .= sprintf(_('Why not [register an account](%%%%action.register%%%%) and then nudge %s or post a notice to them.'), $this->user->nickname);
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showContent()
    {
        if (Event::handle('StartShowAllContent', array($this))) {
            $nl = new NoticeList($this->notice, $this);

            $cnt = $nl->show();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }

            $this->pagination(
                $this->page > 1, $cnt > NOTICES_PER_PAGE,
                $this->page, 'all', array('nickname' => $this->user->nickname)
            );

            Event::handle('EndShowAllContent', array($this));
        }
    }

    function showPageTitle()
    {
        $user = common_current_user();
        if ($user && ($user->id == $this->user->id)) {
            // TRANS: H1 text for page when viewing a list for self.
            $this->element('h1', null, _("You and friends"));
        } else {
            // TRANS: H1 text for page. %s is a user nickname.
            $this->element('h1', null, sprintf(_('%s and friends'), $this->user->nickname));
        }
    }
}

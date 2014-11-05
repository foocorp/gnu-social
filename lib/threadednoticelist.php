<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notices
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
 * @category  UI
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

/**
 * widget for displaying a list of notices
 *
 * There are a number of actions that display a list of notices, in
 * reverse chronological order. This widget abstracts out most of the
 * code for UI for notice lists. It's overridden to hide some
 * data for e.g. the profile page.
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      Notice
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */
class ThreadedNoticeList extends NoticeList
{
    protected $userProfile;

    function __construct(Notice $notice, Action $out=null, $profile=-1)
    {
        parent::__construct($notice, $out);
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        $this->userProfile = $profile;
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */
    function show()
    {
        $this->out->elementStart('div', array('id' =>'notices_primary'));
        // TRANS: Header for Notices section.
        $this->out->element('h2', null, _m('HEADER','Notices'));
        $this->out->elementStart('ol', array('class' => 'notices threaded-notices xoxo'));

		$notices = $this->notice->fetchAll();
		$total = count($notices);
		$notices = array_slice($notices, 0, NOTICES_PER_PAGE);
		
        $allnotices = self::_allNotices($notices);
    	self::prefill($allnotices);
    	
        $conversations = array();
        
        foreach ($notices as $notice) {

            // Collapse repeats into their originals...
            
            if ($notice->repeat_of) {
                $orig = Notice::getKV('id', $notice->repeat_of);
                if ($orig instanceof Notice) {
                    $notice = $orig;
                }
            }
            $convo = $notice->conversation;
            if (!empty($conversations[$convo])) {
                // Seen this convo already -- skip!
                continue;
            }
            $conversations[$convo] = true;

            // Get the convo's root notice
            $root = $notice->conversationRoot($this->userProfile);
            if ($root instanceof Notice) {
                $notice = $root;
            }

            try {
                $item = $this->newListItem($notice);
                $item->show();
            } catch (Exception $e) {
                // we log exceptions and continue
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

        return $total;
    }

    function _allNotices($notices)
    {
        $convId = array();
        foreach ($notices as $notice) {
            $convId[] = $notice->conversation;
        }
        $convId = array_unique($convId);
        $allMap = Notice::listGet('conversation', $convId);
        $allArray = array();
        foreach ($allMap as $convId => $convNotices) {
            $allArray = array_merge($allArray, $convNotices);
        }
        return $allArray;
    }

    /**
     * returns a new list item for the current notice
     *
     * Recipe (factory?) method; overridden by sub-classes to give
     * a different list item class.
     *
     * @param Notice $notice the current notice
     *
     * @return NoticeListItem a list item for displaying the notice
     */
    function newListItem(Notice $notice)
    {
        return new ThreadedNoticeListItem($notice, $this->out, $this->userProfile);
    }
}

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */
class ThreadedNoticeListItem extends NoticeListItem
{
    protected $userProfile = null;

    function __construct(Notice $notice, Action $out=null, $profile=null)
    {
        parent::__construct($notice, $out);
        $this->userProfile = $profile;
    }

    function initialItems()
    {
        return 3;
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        $max = $this->initialItems();
        if (!$this->repeat) {
            $stream = new ConversationNoticeStream($this->notice->conversation, $this->userProfile);
            $notice = $stream->getNotices(0, $max + 2);
            $notices = array();
            $cnt = 0;
            $moreCutoff = null;
            while ($notice->fetch()) {
                if (Event::handle('StartAddNoticeReply', array($this, $this->notice, $notice))) {
                    // Don't list repeats as separate notices in a conversation
                    if (!empty($notice->repeat_of)) {
                        continue;
                    }

                    if ($notice->id == $this->notice->id) {
                        // Skip!
                        continue;
                    }
                    $cnt++;
                    if ($cnt > $max) {
                        // boo-yah
                        $moreCutoff = clone($notice);
                        break;
                    }
                    $notices[] = clone($notice); // *grumble* inefficient as hell
                    Event::handle('EndAddNoticeReply', array($this, $this->notice, $notice));
                }
            }

            if (Event::handle('StartShowThreadedNoticeTail', array($this, $this->notice, &$notices))) {
                $threadActive = count($notices) > 0; // has this thread had any activity?

                $this->out->elementStart('ul', 'notices threaded-replies xoxo');

                if (Event::handle('StartShowThreadedNoticeTailItems', array($this, $this->notice, &$threadActive))) {

                    // Show the repeats-button for this notice. If there are repeats,
                    // the show() function will return true, definitely setting our
                    // $threadActive flag, which will be used later to show a reply box.
                    $item = new ThreadedNoticeListRepeatsItem($this->notice, $this->out);
                    $threadActive = $item->show() || $threadActive;

                    Event::handle('EndShowThreadedNoticeTailItems', array($this, $this->notice, &$threadActive));
                }

                if (count($notices)>0) {
                    if ($moreCutoff) {
                        $item = new ThreadedNoticeListMoreItem($moreCutoff, $this->out, count($notices));
                        $item->show();
                    }
                    foreach (array_reverse($notices) as $notice) {
                        if (Event::handle('StartShowThreadedNoticeSub', array($this, $this->notice, $notice))) {
                            $item = new ThreadedNoticeListSubItem($notice, $this->notice, $this->out);
                            $item->show();
                            Event::handle('EndShowThreadedNoticeSub', array($this, $this->notice, $notice));
                        }
                    }
                }

                if ($threadActive && Profile::current() instanceof Profile) {
                    // @fixme do a proper can-post check that's consistent
                    // with the JS side
                    $item = new ThreadedNoticeListReplyItem($this->notice, $this->out);
                    $item->show();
                }
                $this->out->elementEnd('ul');
                Event::handle('EndShowThreadedNoticeTail', array($this, $this->notice, $notices));
            }
        }

        parent::showEnd();
    }
}

// @todo FIXME: needs documentation.
class ThreadedNoticeListSubItem extends NoticeListItem
{
    protected $root = null;

    function __construct(Notice $notice, $root, $out)
    {
        $this->root = $root;
        parent::__construct($notice, $out);
    }

    function avatarSize()
    {
        return AVATAR_STREAM_SIZE; // @fixme would like something in between
    }

    function showNoticeLocation()
    {
        //
    }

    function showNoticeSource()
    {
        //
    }

    function getReplyProfiles()
    {
        $all = parent::getReplyProfiles();

        $profiles = array();

        $rootAuthor = $this->root->getProfile();

        foreach ($all as $profile) {
            if ($profile->id != $rootAuthor->id) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    function showEnd()
    {
        $threadActive = null;   // unused here for now, but maybe in the future?
        if (Event::handle('StartShowThreadedNoticeTailItems', array($this, $this->notice, &$threadActive))) {
            $item = new ThreadedNoticeListInlineRepeatsItem($this->notice, $this->out);
            $hasRepeats = $item->show();
            Event::handle('EndShowThreadedNoticeTailItems', array($this, $this->notice, &$threadActive));
        }
        parent::showEnd();
    }
}

/**
 * Placeholder for loading more replies...
 */
class ThreadedNoticeListMoreItem extends NoticeListItem
{
    protected $cnt;

    function __construct(Notice $notice, Action $out, $cnt)
    {
        parent::__construct($notice, $out);
        $this->cnt = $cnt;
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showMiniForm();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-reply-comments'));
    }

    function showEnd()
    {
        $this->out->elementEnd('li');
    }

    function showMiniForm()
    {
        $id = $this->notice->conversation;
        $url = common_local_url('conversation', array('id' => $id));

        $n = Conversation::noticeCount($id) - 1;

        // TRANS: Link to show replies for a notice.
        // TRANS: %d is the number of replies to a notice and used for plural.
        $msg = sprintf(_m('Show reply', 'Show all %d replies', $n), $n);

        $this->out->element('a', array('href' => $url), $msg);
    }
}

/**
 * Placeholder for reply form...
 * Same as get added at runtime via SN.U.NoticeInlineReplyPlaceholder
 */
class ThreadedNoticeListReplyItem extends NoticeListItem
{
    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showMiniForm();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-reply-placeholder'));
    }

    function showMiniForm()
    {
        $this->out->element('input', array('class' => 'placeholder',
                                           // TRANS: Field label for reply mini form.
                                           'value' => _('Write a reply...')));
    }
}

/**
 * Placeholder for showing repeats...
 */
class ThreadedNoticeListRepeatsItem extends NoticeListActorsItem
{
    function getProfiles()
    {
        $repeats = $this->notice->getRepeats();

        $profiles = array();

        foreach ($repeats as $rep) {
            $profiles[] = $rep->profile_id;
        }

        return $profiles;
    }

    function magicList($items)
    {
        if (count($items) > 4) {
            return parent::magicList(array_slice($items, 0, 3));
        } else {
            return parent::magicList($items);
        }
    }

    function getListMessage($count, $you)
    {
        if ($count == 1 && $you) {
            // darn first person being different from third person!
            // TRANS: List message for notice repeated by logged in user.
            return _m('REPEATLIST', 'You repeated this.');
        } else if ($count > 4) {
            // TRANS: List message for when more than 4 people repeat something.
            // TRANS: %%s is a list of users liking a notice, %d is the number over 4 that like the notice.
            // TRANS: Plural is decided on the total number of users liking the notice (count of %%s + %d).
            return sprintf(_m('%%s and %d other repeated this.',
                              '%%s and %d others repeated this.',
                              $count - 3),
                           $count - 3);
        } else {
            // TRANS: List message for repeated notices.
            // TRANS: %%s is a list of users who have repeated a notice.
            // TRANS: Plural is based on the number of of users that have repeated a notice.
            return sprintf(_m('%%s repeated this.',
                              '%%s repeated this.',
                              $count),
                           $count);
        }
    }

    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-data notice-repeats'));
    }

    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}

// @todo FIXME: needs documentation.
class ThreadedNoticeListInlineRepeatsItem extends ThreadedNoticeListRepeatsItem
{
    function showStart()
    {
        $this->out->elementStart('div', array('class' => 'notice-repeats'));
    }

    function showEnd()
    {
        $this->out->elementEnd('div');
    }
}

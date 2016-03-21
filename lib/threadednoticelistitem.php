<?php

if (!defined('GNUSOCIAL')) { exit(1); }

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
        if (!$this->repeat instanceof Notice) {
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
                    // Repeats and Faves/Likes are handled in plugins.
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

                Event::handle('EndShowThreadedNoticeTail', array($this, $this->notice, $notices));
                $this->out->elementEnd('ul');
            }
        }

        parent::showEnd();
    }
}

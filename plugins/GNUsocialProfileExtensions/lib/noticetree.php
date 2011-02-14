<?php
/**
 * GNU Social
 * Copyright (C) 2010, Free Software Foundation, Inc.
 *
 * PHP version 5
 *
 * LICENCE:
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
 * @category  Widget
 * @package   GNU Social
 * @author    Max Shinn <trombonechamp@gmail.com>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

class NoticeTree extends NoticeList
{
    function show()
    {
        $this->out->elementStart('div', array('id' =>'notices_primary'));
        // TRANS: Header on conversation page. Hidden by default (h2).
        $this->out->element('h2', null, _('Notices'));
        $this->out->elementStart('ol', array('class' => 'notices xoxo'));

        $cnt = 0;

        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            if (!empty($this->notice->reply_to))
                continue;

            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            try {
                $this->showNoticePlus($this->notice);
            } catch (Exception $e) {
                // we log exceptions and continue
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

        return $cnt;
    }


    function showNoticePlus($notice)
    {
        $replies = new Notice();
        $replies->reply_to = $notice->id;

        // We take responsibility for doing the li

         $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'notice-' . $id));

        $item = $this->newListItem($notice);
        $item->show();

        if ($replies->find()) {
            $this->out->elementStart('ol', array('class' => 'notices'));

            while ($replies->fetch()) {
                $this->showNoticePlus($replies);
            }

            $this->out->elementEnd('ol');
        }

        $this->out->elementEnd('li');
    }

    function newListItem($notice)
    {
        return new NoticeTreeItem($notice, $this->out);
    }
}

class NoticeTreeItem extends NoticeListItem
{
    function showStart()
    {
        return;
    }

    function showEnd()
    {
        //TODO: Rewrite this
        //Showing number of favorites
        $fave = new Fave();
        $fave->notice_id = $this->notice->id;
        $cnt = 0;
        if ($fave->find()) {
            while ($fave->fetch())
                $cnt++;
        }
        if ($cnt > 0) {
            $this->out->text(_m("Favorited by $cnt user"));
            if ($cnt > 1) $this->out->text("s"); //there has to be a better way to do this...
        }

        //TODO: Rewrite this too
        //Show response form
        $this->out->elementStart('div', array('id' => 'form' . $this->notice->id, 'class' => 'replyform'));
        $noticeform = new NoticeForm($this->out, null, null, null, $this->notice->id);
        $noticeform->show();
        $this->out->elementEnd('div');
        return;
    }

    function showContext()
    {
        return;
    }

    //Just changing the link...
    function showReplyLink()
    {
        if (common_logged_in()) {
            $this->out->text(' ');
            $reply_url = '/notice/respond?replyto=' . $this->profile->nickname . '&inreplyto=' . $this->notice->id;
            $this->out->elementStart('a', array('href' => $reply_url,
                                                'class' => 'notice_reply',
                                                'title' => _('Reply to this notice')));
            $this->out->text(_('Reply'));
            $this->out->text(' ');
            $this->out->element('span', 'notice_id', $this->notice->id);
            $this->out->elementEnd('a');
        }
    }

}

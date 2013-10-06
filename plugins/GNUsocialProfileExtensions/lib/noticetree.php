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
            //functions to sort replies
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
                $this->showNoticePlus($this->newListItem($this->notice));
            } catch (Exception $e) {
                // we log exceptions and continue
                print "ERROR!" . $e->getMessage();
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

        return $cnt;
    }

    function _cmpDate($a, $b) { return strcmp($a->notice->modified, $b->notice->modified); }
    function _cmpFav($a, $b) { return (int)$a->faves < (int)$b->faves; }

    function showNoticePlus($item)
    {
        $replies = new Notice();
        $replies->reply_to = $item->notice->id;

        // We take responsibility for doing the li

        $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'notice-' . $item->notice->id));

        $item->show();

        if ($replies->find()) {
            $this->out->elementStart('ol', array('class' => 'notices'));

            $replieslist = array();
            while ($replies->fetch()) {
                $replieslist[] = $this->newListItem(clone($replies));
            }

            //Sorting based on url argument
            if($_GET['sort'] == 'faves')
                usort($replieslist, array($this, '_cmpFav'));
            else
                usort($replieslist, array($this, '_cmpDate'));


            foreach($replieslist as $reply) {
                $this->showNoticePlus($reply);
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
    function __construct($notice, $out=null)
    {
        parent::__construct($notice, $out);
        //TODO: Rewrite this
        //Showing number of favorites
        $fave = new Fave();
        $fave->notice_id = $this->notice->id;
        $cnt = 0;
        if ($fave->find()) {
            while ($fave->fetch())
                $cnt++;
        }
        $this->faves = $cnt;
    }        

    function showStart()
    {
        return;
    }

    function showEnd()
    {
        if ($this->faves > 0) {
            $this->out->text(_m("Favorited by $this->faves user"));
            if ($this->faves > 1) $this->out->text("s"); //there has to be a better way to do this...
        }

        //Show response form
        $this->out->elementStart('div', array('id' => 'form' . $this->notice->id, 'class' => 'replyform'));
        $noticeform = new ReplyForm($this->out, null, null, null, $this->notice->id);
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
            //Why doesn't common_local_url work here?
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

class ReplyForm extends NoticeForm
{
    //Changing the text.  We have to either repeat ids or get hacky.  I vote repeat ids.
    function formData()
    {
        if (Event::handle('StartShowNoticeFormData', array($this))) {
            // XXX: vary by defined max size
            $this->out->element('textarea', array('id' => 'notice_data-text',
                                                  'cols' => 35,
                                                  'rows' => 4,
                                                  'name' => 'status_textarea'),
                                ($this->content) ? $this->content : '');

            $contentLimit = Notice::maxContent();

            if ($contentLimit > 0) {
                $this->out->elementStart('dl', 'form_note');
                $this->out->element('dt', null, _('Available characters'));
                $this->out->element('dd', array('id' => 'notice_text-count'),
                                    $contentLimit);
                $this->out->elementEnd('dl');
            }

            if (common_config('attachments', 'uploads')) {
                $this->out->element('label', array('for' => 'notice_data-attach'),_('Attach'));
                $this->out->element('input', array('id' => 'notice_data-attach',
                                                   'type' => 'file',
                                                   'name' => 'attach',
                                                   'title' => _('Attach a file')));
                $this->out->hidden('MAX_FILE_SIZE', common_config('attachments', 'file_quota'));
            }
            if ($this->action) {
                $this->out->hidden('notice_return-to', $this->action, 'returnto');
            }
            $this->out->hidden('notice_in-reply-to', $this->inreplyto, 'inreplyto');

            if ($this->profile->shareLocation()) {
                $this->out->hidden('notice_data-lat', empty($this->lat) ? (empty($this->profile->lat) ? null : $this->profile->lat) : $this->lat, 'lat');
                $this->out->hidden('notice_data-lon', empty($this->lon) ? (empty($this->profile->lon) ? null : $this->profile->lon) : $this->lon, 'lon');
                $this->out->hidden('notice_data-location_id', empty($this->location_id) ? (empty($this->profile->location_id) ? null : $this->profile->location_id) : $this->location_id, 'location_id');
                $this->out->hidden('notice_data-location_ns', empty($this->location_ns) ? (empty($this->profile->location_ns) ? null : $this->profile->location_ns) : $this->location_ns, 'location_ns');

                $this->out->elementStart('div', array('id' => 'notice_data-geo_wrap',
                                                      'title' => common_local_url('geocode')));
                $this->out->checkbox('notice_data-geo', _('Share my location'), true);
                $this->out->elementEnd('div');
                $this->out->inlineScript(' var NoticeDataGeo_text = {'.
                    'ShareDisable: ' .json_encode(_('Do not share my location')).','.
                    'ErrorTimeout: ' .json_encode(_('Sorry, retrieving your geo location is taking longer than expected, please try again later')).
                    '}');
            }

            Event::handle('EndShowNoticeFormData', array($this));
        }
    }
}

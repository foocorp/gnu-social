<?php
/*
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class TagAction extends Action
{

    var $notice;

    function prepare($args)
    {
        parent::prepare($args);
        $taginput = $this->trimmed('tag');
        $this->tag = common_canonical_tag($taginput);

        if (!$this->tag) {
            common_redirect(common_local_url('publictagcloud'), 301);
            return false;
        }

        if ($this->tag != $taginput) {
            common_redirect(common_local_url('tag', array('tag' => $this->tag)),
                            301);
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_set_returnto($this->selfUrl());

        $this->notice = Notice_tag::getStream($this->tag, (($this->page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

        if($this->page > 1 && $this->notice->N == 0){
            // TRANS: Server error when page not found (404)
            $this->serverError(_('No such page.'),$code=404);
        }

        return true;
    }

    function showSections()
    {
        $pop = new PopularNoticeSection($this);
        $pop->show();
    }

    function title()
    {
        if ($this->page == 1) {
            return sprintf(_('Notices tagged with %s'), $this->tag);
        } else {
            return sprintf(_('Notices tagged with %1$s, page %2$d'),
                           $this->tag,
                           $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    function getFeeds()
    {
        return array(new Feed(Feed::RSS1,
                              common_local_url('tagrss',
                                               array('tag' => $this->tag)),
                              sprintf(_('Notice feed for tag %s (RSS 1.0)'),
                                      $this->tag)),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelineTag',
                                               array('format' => 'rss',
                                                     'tag' => $this->tag)),
                              sprintf(_('Notice feed for tag %s (RSS 2.0)'),
                                      $this->tag)),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelineTag',
                                               array('format' => 'atom',
                                                     'tag' => $this->tag)),
                              sprintf(_('Notice feed for tag %s (Atom)'),
                                      $this->tag)));
    }

    function showContent()
    {
        if(Event::handle('StartTagShowContent', array($this))) {
            
            $nl = new NoticeList($this->notice, $this);

            $cnt = $nl->show();

            $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                              $this->page, 'tag', array('tag' => $this->tag));

            Event::handle('EndTagShowContent', array($this));
        }
    }

    function isReadOnly($args)
    {
        return true;
    }
}

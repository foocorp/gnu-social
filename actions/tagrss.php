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

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action
class TagrssAction extends Rss10Action
{
    var $tag;

    function prepare($args) {
        parent::prepare($args);
        $tag = common_canonical_tag($this->trimmed('tag'));
        $this->tag = Notice_tag::getKV('tag', $tag);
        if (!$this->tag) {
            // TRANS: Client error when requesting a tag feed for a non-existing tag.
            $this->clientError(_('No such tag.'));
        } else {
            $this->notices = $this->getNotices($this->limit);
            return true;
        }
    }

    function getNotices($limit=0)
    {
        $tag = $this->tag;

        if (is_null($tag)) {
            return null;
        }

        $notice = Notice_tag::getStream($tag->tag)->getNotices(0, ($limit == 0) ? NOTICES_PER_PAGE : $limit);
        return $notice->fetchAll();
    }

    function getChannel()
    {
        $tagname = $this->tag->tag;
        $c = array('url' => common_local_url('tagrss', array('tag' => $tagname)),
               'title' => $tagname,
               'link' => common_local_url('tagrss', array('tag' => $tagname)),
               // TRANS: Tag feed description.
               // TRANS: %1$s is the tag name, %2$s is the StatusNet sitename.
               'description' => sprintf(_('Updates tagged with %1$s on %2$s!'),
                                        $tagname, common_config('site', 'name')));
        return $c;
    }

    function isReadOnly($args)
    {
        return true;
    }
}

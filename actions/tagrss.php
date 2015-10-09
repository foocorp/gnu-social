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

if (!defined('GNUSOCIAL')) { exit(1); }

// Formatting of RSS handled by Rss10Action

class TagrssAction extends Rss10Action
{
    protected $tag;

    protected function doStreamPreparation()
    {
        $tag = common_canonical_tag($this->trimmed('tag'));
        $this->tag = Notice_tag::getKV('tag', $tag);
        if (!$this->tag instanceof Notice_tag) {
            // TRANS: Client error when requesting a tag feed for a non-existing tag.
            $this->clientError(_('No such tag.'));
        }
    }

    protected function getNotices()
    {
        $stream = Notice_tag::getStream($this->tag->tag)->getNotices(0, $this->limit);
        return $stream->fetchAll();
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

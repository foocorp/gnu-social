<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Attachment tag cloud section
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Attachment tag cloud section
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AttachmentTagCloudSection extends TagCloudSection
{
    function title()
    {
        // TRANS: Title.
        return _('Tags for this attachment');
    }

    function showTag($tag, $weight, $relative)
    {
        if ($relative > 0.5) {
            $rel =  'tag-cloud-7';
        } else if ($relative > 0.4) {
            $rel = 'tag-cloud-6';
        } else if ($relative > 0.3) {
            $rel = 'tag-cloud-5';
        } else if ($relative > 0.2) {
            $rel = 'tag-cloud-4';
        } else if ($relative > 0.1) {
            $rel = 'tag-cloud-3';
        } else if ($relative > 0.05) {
            $rel = 'tag-cloud-2';
        } else {
            $rel = 'tag-cloud-1';
        }

        $this->out->elementStart('li', $rel);
        $this->out->element('a', array('href' => $this->tagUrl($tag)),
                       $tag);
        $this->out->elementEnd('li');
    }

    function getTags()
    {
        $notice_tag = new Notice_tag;
        $query = 'select tag,count(tag) as weight from notice_tag join file_to_post on (notice_tag.notice_id=post_id) join notice on notice_id = notice.id where file_id=' . $notice_tag->escape($this->out->attachment->id) . ' group by tag order by weight desc';
        $notice_tag->query($query);
        return $notice_tag;
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * FIXME
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
 * FIXME
 *
 * These are the widgets that show interesting data about a person * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AttachmentNoticeSection extends NoticeSection
{
    function showContent() {
        parent::showContent();
        return false;
    }

    function getNotices()
    {
        $notice = new Notice;

        $notice->joinAdd(array('id', 'file_to_post:post_id'));
        $notice->whereAdd(sprintf('file_to_post.file_id = %d', $this->out->attachment->id));

        $notice->orderBy('created desc');
        $notice->selectAdd('post_id as id');
        $notice->find();
        return $notice;
    }

    function title()
    {
        // TRANS: Title.
        return _('Notices where this attachment appears');
    }

    function divId()
    {
        return 'attachment_section';
    }
}

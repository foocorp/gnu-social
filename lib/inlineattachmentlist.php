<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying notice attachments thumbnails
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class InlineAttachmentList extends AttachmentList
{
    function showListStart()
    {
        $this->out->elementStart('div', array('class' => 'attachments'));
    }

    function showListEnd()
    {
        $this->out->elementEnd('div');
    }

    /**
     * returns a new list item for the current attachment
     *
     * @param File $notice the current attachment
     *
     * @return ListItem a list item for displaying the attachment
     */
    function newListItem(File $attachment)
    {
        return new InlineAttachmentListItem($attachment, $this->out);
    }
}

class InlineAttachmentListItem extends AttachmentListItem
{
    function showLink() {
        $this->out->element('a', $this->linkAttr(), $this->title());
        $this->showRepresentation();
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        $this->out->elementStart('span', array('class' => 'inline-attachment'));
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
        $this->out->elementEnd('span');
    }
}

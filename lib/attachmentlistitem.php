<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notice attachments
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

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
class AttachmentListItem extends Widget
{
    /** The attachment this item will show. */

    var $attachment = null;

    /**
     * @param File $attachment the attachment we will display
     */
    function __construct(File $attachment, $out=null)
    {
        parent::__construct($out);
        $this->attachment  = $attachment;
    }

    function title() {
        return $this->attachment->title ?: $this->attachment->filename;
    }

    function linkTitle() {
        return $this->title();
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
        $this->showNoticeAttachment();
        $this->showEnd();
    }

    function linkAttr() {
        return array('class' => 'attachment',
                     'href' => $this->attachment->url,
                     'id' => 'attachment-' . $this->attachment->id,
                     'title' => $this->linkTitle());
    }

    function showLink() {
        $this->out->elementStart('a', $this->linkAttr());
        $this->out->element('span', null, $this->linkTitle());
        $this->showRepresentation();
        $this->out->elementEnd('a');
    }

    function showNoticeAttachment()
    {
        $this->showLink();
    }

    function showRepresentation() {
        try {
            $thumb = $this->attachment->getThumbnail();
            $this->out->element('img', array('alt' => '', 'src' => $thumb->getUrl(), 'width' => $thumb->width, 'height' => $thumb->height));
        } catch (UnsupportedMediaException $e) {
            // Image representation unavailable
        }
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
        $this->out->elementStart('li');
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
        $this->out->elementEnd('li');
    }
}

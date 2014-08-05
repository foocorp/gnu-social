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
 * widget for displaying a list of notice attachments
 *
 * There are a number of actions that display a list of notices, in
 * reverse chronological order. This widget abstracts out most of the
 * code for UI for notice lists. It's overridden to hide some
 * data for e.g. the profile page.
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      Notice
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */
class AttachmentList extends Widget
{
    /** the current stream of notices being displayed. */

    var $notice = null;

    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */
    function __construct($notice, $out=null)
    {
        parent::__construct($out);
        $this->notice = $notice;
    }

    /**
     * show the list of attachments
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of items listed.
     */
    function show()
    {
    	$attachments = $this->notice->attachments();
        $representable = false;
        foreach ($attachments as $key=>$att) {
            // Only show attachments representable with a title
            if ($att->getTitle() === null) {
                unset($attachments[$key]);
            }
        }
        if (!count($attachments)) {
            return 0;
        }

        $this->showListStart();

        foreach ($attachments as $att) {
            $item = $this->newListItem($att);
            $item->show();
        }

        $this->showListEnd();

        return count($attachments);
    }

    function showListStart()
    {
        $this->out->elementStart('ol', array('class' => 'attachments'));
    }

    function showListEnd()
    {
        $this->out->elementEnd('ol');
    }

    /**
     * returns a new list item for the current attachment
     *
     * @param File $attachment the current attachment
     *
     * @return AttachmentListItem a list item for displaying the attachment
     */
    function newListItem(File $attachment)
    {
        return new AttachmentListItem($attachment, $this->out);
    }
}

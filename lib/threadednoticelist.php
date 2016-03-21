<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notices
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
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * widget for displaying a list of notices
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
class ThreadedNoticeList extends NoticeList
{
    protected $userProfile;

    function __construct(Notice $notice, Action $out=null, $profile=-1)
    {
        parent::__construct($notice, $out);
        if (is_int($profile) && $profile == -1) {
            $profile = Profile::current();
        }
        $this->userProfile = $profile;
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */
    function show()
    {
        $this->out->elementStart('div', array('id' =>'notices_primary'));
        // TRANS: Header for Notices section.
        $this->out->element('h2', null, _m('HEADER','Notices'));
        $this->out->elementStart('ol', array('class' => 'notices threaded-notices xoxo'));

		$notices = $this->notice->fetchAll();
		$total = count($notices);
		$notices = array_slice($notices, 0, NOTICES_PER_PAGE);
		
        $allnotices = self::_allNotices($notices);
    	self::prefill($allnotices);
    	
        $conversations = array();
        
        foreach ($notices as $notice) {

            // Collapse repeats into their originals...
            
            if ($notice->repeat_of) {
                $orig = Notice::getKV('id', $notice->repeat_of);
                if ($orig instanceof Notice) {
                    $notice = $orig;
                }
            }
            $convo = $notice->conversation;
            if (!empty($conversations[$convo])) {
                // Seen this convo already -- skip!
                continue;
            }
            $conversations[$convo] = true;

            // Get the convo's root notice
            $root = $notice->conversationRoot($this->userProfile);
            if ($root instanceof Notice) {
                $notice = $root;
            }

            try {
                $item = $this->newListItem($notice);
                $item->show();
            } catch (Exception $e) {
                // we log exceptions and continue
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

        return $total;
    }

    function _allNotices($notices)
    {
        $convId = array();
        foreach ($notices as $notice) {
            $convId[] = $notice->conversation;
        }
        $convId = array_unique($convId);
        $allMap = Notice::listGet('conversation', $convId);
        $allArray = array();
        foreach ($allMap as $convId => $convNotices) {
            $allArray = array_merge($allArray, $convNotices);
        }
        return $allArray;
    }

    /**
     * returns a new list item for the current notice
     *
     * Recipe (factory?) method; overridden by sub-classes to give
     * a different list item class.
     *
     * @param Notice $notice the current notice
     *
     * @return NoticeListItem a list item for displaying the notice
     */
    function newListItem(Notice $notice)
    {
        return new ThreadedNoticeListItem($notice, $this->out, $this->userProfile);
    }
}

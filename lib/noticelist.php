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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

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
class NoticeList extends Widget
{
    /** the current stream of notices being displayed. */

    var $notice = null;

    protected $addressees = true;
    protected $attachments = true;
    protected $id_prefix = null;
    protected $maxchars = 0;
    protected $options = true;
    protected $show_n = NOTICES_PER_PAGE;

    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */
    function __construct(Notice $notice, $out=null, array $prefs=array())
    {
        parent::__construct($out);
        $this->notice = $notice;

        // integer preferences
        foreach(array('show_n', 'maxchars') as $key) {
            if (array_key_exists($key, $prefs)) {
                $this->$key = (int)$prefs[$key];
            }
        }
        // boolean preferences
        foreach(array('addressees', 'attachments', 'options') as $key) {
            if (array_key_exists($key, $prefs)) {
                $this->$key = (bool)$prefs[$key];
            }
        }
        // string preferences
        foreach(array('id_prefix') as $key) {
            if (array_key_exists($key, $prefs)) {
                $this->$key = $prefs[$key];
            }
        }
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @param integer $n    The amount of notices to show.
     *
     * @return int  Total amount of notices actually available.
     */
    public function show()
    {
        $this->out->elementStart('ol', array('class' => 'notices xoxo'));

		$notices = $this->notice->fetchAll();
		$total   = count($notices);
		$notices = array_slice($notices, 0, $this->show_n);
		
    	self::prefill($notices);
    	
    	foreach ($notices as $notice) {

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
        return $total;
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
        $prefs = array('addressees' => $this->addressees,
                       'attachments' => $this->attachments,
                       'id_prefix' => $this->id_prefix,
                       'maxchars' => $this->maxchars,
                       'options' => $this->options);
        return new NoticeListItem($notice, $this->out, $prefs);
    }
    
    static function prefill(array &$notices)
    {
        $scoped = Profile::current();
        $notice_ids = Notice::_idsOf($notices);

        if (Event::handle('StartNoticeListPrefill', array(&$notices, $notice_ids, $scoped))) {

            // Prefill attachments
            Notice::fillAttachments($notices);
            // Prefill the profiles
            $profiles = Notice::fillProfiles($notices);

            Event::handle('EndNoticeListPrefill', array(&$notices, &$profiles, $notice_ids, $scoped));
        }
    }
}

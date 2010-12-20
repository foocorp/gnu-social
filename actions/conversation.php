<?php
/**
 * Display a conversation in the browser
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// XXX: not sure how to do paging yet,
// so set a 60-notice limit

require_once INSTALLDIR.'/lib/noticelist.php';

/**
 * Conversation tree in the browser
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ConversationAction extends Action
{
    var $id   = null;
    var $page = null;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if id not passed in
     */
    function prepare($args)
    {
        parent::prepare($args);
        $this->id = $this->trimmed('id');
        if (empty($this->id)) {
            return false;
        }
        $this->id = $this->id+0;
        $this->page = $this->trimmed('page');
        if (empty($this->page)) {
            $this->page = 1;
        }
        return true;
    }

    /**
     * Handle the action
     *
     * @param array $args Web and URL arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title for page with a conversion (multiple notices in context).
        return _('Conversation');
    }

    /**
     * Show content.
     *
     * Display a hierarchical unordered list in the content area.
     * Uses ConversationTree to do most of the heavy lifting.
     *
     * @return void
     */
    function showContent()
    {
        $notices = Notice::conversationStream($this->id, null, null);

        $ct = new ConversationTree($notices, $this);

        $cnt = $ct->show();
    }

    function isReadOnly()
    {
        return true;
    }
}

/**
 * Conversation tree
 *
 * The widget class for displaying a hierarchical list of notices.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ConversationTree extends NoticeList
{
    var $tree  = null;
    var $table = null;

    /**
     * Show the tree of notices
     *
     * @return void
     */
    function show()
    {
        $cnt = $this->_buildTree();

        $this->out->elementStart('div', array('id' =>'notices_primary'));
        // TRANS: Header on conversation page. Hidden by default (h2).
        $this->out->element('h2', null, _('Notices'));
        $this->out->elementStart('ol', array('class' => 'notices xoxo'));

        if (array_key_exists('root', $this->tree)) {
            $rootid = $this->tree['root'][0];
            $this->showNoticePlus($rootid);
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

        return $cnt;
    }

    function _buildTree()
    {
        $cnt = 0;

        $this->tree  = array();
        $this->table = array();

        while ($this->notice->fetch()) {

            $cnt++;

            $id     = $this->notice->id;
            $notice = clone($this->notice);

            $this->table[$id] = $notice;

            if (is_null($notice->reply_to)) {
                $this->tree['root'] = array($notice->id);
            } else if (array_key_exists($notice->reply_to, $this->tree)) {
                $this->tree[$notice->reply_to][] = $notice->id;
            } else {
                $this->tree[$notice->reply_to] = array($notice->id);
            }
        }

        return $cnt;
    }

    /**
     * Shows a notice plus its list of children.
     *
     * @param integer $id ID of the notice to show
     *
     * @return void
     */
    function showNoticePlus($id)
    {
        $notice = $this->table[$id];

        // We take responsibility for doing the li

        $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'notice-' . $id));

        $item = $this->newListItem($notice);
        $item->show();

        if (array_key_exists($id, $this->tree)) {
            $children = $this->tree[$id];

            $this->out->elementStart('ol', array('class' => 'notices'));

            sort($children);

            foreach ($children as $child) {
                $this->showNoticePlus($child);
            }

            $this->out->elementEnd('ol');
        }

        $this->out->elementEnd('li');
    }

    /**
     * Override parent class to return our preferred item.
     *
     * @param Notice $notice Notice to display
     *
     * @return NoticeListItem a list item to show
     */
    function newListItem($notice)
    {
        return new ConversationTreeItem($notice, $this->out);
    }
}

/**
 * Conversation tree list item
 *
 * Special class of NoticeListItem for use inside conversation trees.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ConversationTreeItem extends NoticeListItem
{
    /**
     * start a single notice.
     *
     * The default creates the <li>; we skip, since the ConversationTree
     * takes care of that.
     *
     * @return void
     */
    function showStart()
    {
        return;
    }

    /**
     * finish the notice
     *
     * The default closes the <li>; we skip, since the ConversationTree
     * takes care of that.
     *
     * @return void
     */
    function showEnd()
    {
        return;
    }

    /**
     * show link to notice conversation page
     *
     * Since we're only used on the conversation page, we skip this
     *
     * @return void
     */
    function showContext()
    {
        return;
    }
}

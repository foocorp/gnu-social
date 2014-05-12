<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Conversation tree widget for oooooold school playas
 * 
 * PHP version 5
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
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

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
        $this->out->elementStart('ol', array('class' => 'notices xoxo old-school'));

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

        $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'notice-' . $id));

        $item = $this->newListItem($notice);
        $item->show();

        if (array_key_exists($id, $this->tree)) {
            $children = $this->tree[$id];

            $this->out->elementStart('ol', array('class' => 'notices threaded-replies xoxo'));

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

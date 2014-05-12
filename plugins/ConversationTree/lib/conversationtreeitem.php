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

    /**
     * show people this notice is in reply to
     *
     * Tree context shows this, so we skip it.
     *
     * @return void
     */
    function showAddressees()
    {
        return;
    }
}

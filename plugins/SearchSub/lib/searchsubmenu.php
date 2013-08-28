<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Menu to show searches you're subscribed to
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
 * @category  Menu
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Class comment
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class SearchSubMenu extends MoreMenu
{
    protected $user;
    protected $searches;

    function __construct($out, $user, $searches)
    {
        parent::__construct($out);
        $this->user = $user;
        $this->searches = $searches;
    }

    function tag()
    {
        return 'searchsubs';
    }

    function seeAllItem()
    {
        return array('searchsubs',
                     array('nickname' => $this->user->nickname),
                     _('See all'),
                     _('See all searches you are following'));
    }

    function getItems()
    {
        $items = array();
        
        foreach ($this->searches as $search) {
            if (!empty($search)) {
                $items[] = array('noticesearch',
                                 array('q' => $search),
                                 sprintf('"%s"', $search),
                                 sprintf(_('Notices including %s'), $search));;
            }
        }

        return $items;
    } 

    function item($actionName, $args, $label, $description, $id=null, $cls=null)
    {
        if (empty($id)) {
            $id = $this->menuItemID($actionName, $args);
        }

        if ($actionName == 'noticesearch') {
            // Add 'q' as a search param, not part of the url path
            $url = common_local_url($actionName, array(), $args);
        } else {
            $url = common_local_url($actionName, $args);
        }

        $this->out->menuItem($url,
                             $label,
                             $description,
                             $this->isCurrent($actionName, $args),
                             $id,
                             $cls);
    }
}


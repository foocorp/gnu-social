<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A menu with a More... button to show more elements
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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A menu with a More... element to show more items
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class MoreMenu extends Menu
{
    const SOFT_MAX = 5;
    const HARD_MAX = 15;

    /**
     * Show a menu with a limited number of elements
     *
     * @param
     *
     * @return
     */
    function show()
    {
        $items = $this->getItems();
        $tag = $this->tag();
        $menuID  = null;

        $attrs = array('class' => 'nav');

        if (!is_null($tag)) {
            $menuID = 'nav_' . $tag;
            $attrs['id'] = $menuID;
        }

        if (Event::handle('StartNav', array($this, &$tag, &$items))) {
            $this->out->elementStart('ul', $attrs);

            $total = count($items);

            if ($total <= self::SOFT_MAX + 1) {
                $toShow = $items;
            } else {
                $toShow = array_slice($items, 0, self::SOFT_MAX);
            }

            foreach ($toShow as $item) {
            	if (count($item) == 5) {
                	list($actionName, $args, $label, $description, $id) = $item;
            	} else {
                	list($actionName, $args, $label, $description) = $item;
                	$id = null;            	    
            	}
                $this->item($actionName, $args, $label, $description, $id);
            }

            if ($total > self::SOFT_MAX + 1) {
                $this->out->elementStart('li', array('class' => 'more_link'));
                $this->out->element('a', array('href' => '#',
                                               'onclick' => 'SN.U.showMoreMenuItems("'.$menuID.'"); return false;'),
                                    // TRANS: Link description to show more items in a list.
                                    _('More ▼'));
                $this->out->elementEnd('li');

                $extended = array_slice($items, self::SOFT_MAX, self::HARD_MAX - self::SOFT_MAX);

                foreach ($extended as $item) {
            		if (count($item) == 5) {
                		list($actionName, $args, $label, $description, $id) = $item;
            		} else {
                		list($actionName, $args, $label, $description) = $item;
                		$id = null;            	    
            		}
                    $this->item($actionName, $args, $label, $description, $id, 'extended_menu');
                }

                if ($total > self::HARD_MAX) {
                    $seeAll = $this->seeAllItem();

                    if (!empty($seeAll)) {
            			if (count($seeAll) == 5) {
                			list($actionName, $args, $label, $description, $id) = $seeAll;
            			} else {
                			list($actionName, $args, $label, $description) = $seeAll;
                			$id = null;            	    
            			}
                        $this->item($actionName, $args, $label, $description, $id, 'extended_menu see_all');
                    }
                }
            }

            $this->out->elementEnd('ul');

            Event::handle('EndNav', array($this, $tag, $items));
        }
    }

    function seeAllItem()
    {
        return null;
    }
}

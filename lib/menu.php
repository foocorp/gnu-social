<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Menu widget
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
 * Superclass for menus
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Menu extends Widget
{
    var $action     = null;
    var $actionName = null;
    var $actionArgs = null;
    
    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */
    function __construct(Action $action=null)
    {
        parent::__construct($action);

        $this->action     = $action;
        $this->actionName = $action->trimmed('action');

        $rtargs = $action->returnToArgs();

        $this->actionArgs = $rtargs[1];
    }

    function getItems()
    {
        return array();
    }

    function tag()
    {
        return null;
    }
    
    function show()
    {
        $items = $this->getItems();
        $tag = $this->tag();

        $attrs = array('class' => 'nav');

        if (!is_null($tag)) {
            $attrs['id']  = 'nav_' . $tag;
        }

        if (Event::handle('StartNav', array($this, &$tag, &$items))) {

            $this->out->elementStart('ul', $attrs);

            foreach ($items as $item) {
                list($actionName, $args, $label, $description, $id) = $item;
                $this->item($actionName, $args, $label, $description, $id);
            }
        
            $this->out->elementEnd('ul');
            
            Event::handle('EndNav', array($this, $tag, $items));
        }
    }
    
    function item($actionName, array $args, $label, $description, $id=null, $cls=null)
    {
        if (empty($id)) {
            $id = $this->menuItemID($actionName, $args);
        }

        $url = common_local_url($actionName, $args);

        $this->out->menuItem($url,
                             $label,
                             $description,
                             $this->isCurrent($actionName, $args),
                             $id,
                             $cls);
    }

    function isCurrent($actionName, array $args)
    {
        if ($actionName != $this->actionName) {
            return false;
        }

        foreach ($this->actionArgs as $k => $v) {
            if (!array_key_exists($k, $args) || $args[$k] != $v) {
                return false;
            }
        }

        return true;
    }
    
    function menuItemID($actionName, $args = null)
    {
        $id = sprintf('nav_%s', $actionName);
        
        if (!is_null($args)) {
            foreach ($args as $key => $value) {
                $id .= '_' . $key . '_' . $value;
            }
        }

        return $id;
    }

    function submenu($label, $menu)
    {
        if (empty($menu->getItems())){
            return false;
        }
        $this->action->elementStart('li');
        $this->action->element('h3', null, $label);
        $menu->show();
        $this->action->elementEnd('li');
    }
}

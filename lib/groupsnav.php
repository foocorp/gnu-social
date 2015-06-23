<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Menu for streams
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
 * @category  Cache
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
 * Menu for streams you follow
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupsNav extends MoreMenu
{
    protected $user;
    protected $groups;

    function __construct($action, $user)
    {
        parent::__construct($action);
        $this->user = $user;
        $this->groups = $user->getGroups();
    }

    function haveGroups()
    {
        return ($this->groups instanceof User_group && $this->groups->N > 0);
    }

    function tag()
    {
        return 'groups';
    }

    function getItems()
    {
        $items = array();

        while ($this->groups instanceof User_group && $this->groups->fetch()) {
            $items[] = array('placeholder',
                             array('nickname' => $this->groups->nickname,
                                   'mainpage' => $this->groups->homeUrl()),
                             $this->groups->getBestName(),
                             $this->groups->getBestName()
                            );
        }

        return $items;
    }

    function seeAllItem() {
        return array('usergroups',
                     array('nickname' => $this->user->nickname),
                     // TRANS: Link description for seeing all groups.
                     _('See all'),
                     // TRANS: Link title for seeing all groups.
                     _('See all groups you belong to.'));
    }

    function item($actionName, array $args, $label, $description, $id=null, $cls=null)
    {
        if ($actionName != 'placeholder') {
            return parent::item($actionName, $args, $label, $description, $id, $cls);
        }

        if (empty($id)) {
            $id = $this->menuItemID('showgroup', array('nickname' => $args['nickname']));
        }

        $url = $args['mainpage'];

        $this->out->menuItem($url,
                             $label,
                             $description,
                             $this->isCurrent($actionName, $args),
                             $id,
                             $cls);
    }
}

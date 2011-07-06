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
class GroupsNav extends Menu
{
	const TOP_GROUPS = 5;
	
    protected $user;
    protected $groups;

    function __construct($action, $user)
    {
        parent::__construct($action);
        $this->user = $user;
        $this->groups = $this->getTopGroups($user);
    }

    function haveGroups()
    {
        return (!empty($this->groups) && ($this->groups->N > 0));
    }

	function getTopGroups($user)
	{
        $memberships = Group_member::byMember($user->id,
                                              0,
                                              self::TOP_GROUPS + 1);

        $g = array();

        while ($memberships->fetch()) {
            $g[] = User_group::staticGet('id', $memberships->group_id);
        }
        
        return new ArrayWrapper($g);
	}

    /**
     * Show the menu
     *
     * @return void
     */
    function show()
    {
        $action = $this->actionName;

        $this->out->elementStart('ul', array('class' => 'nav',
                                             'id' => 'nav_group'));

        if (Event::handle('StartGroupsNav', array($this))) {

			$cnt = 0;
			
            while ($this->groups->fetch()) {
            	
            	$cnt++;
            	
            	if ($cnt > self::TOP_GROUPS) {
            		break;
            	}
            	
                $this->out->menuItem(($this->groups->mainpage) ?
                                     $this->groups->mainpage :
                                     common_local_url('showgroup',
                                                      array('nickname' => $this->groups->nickname)),
                                     $this->groups->getBestName(),
                                     '',
                                     $action == 'showgroup' &&
                                     $this->action->arg('nickname') == $this->groups->nickname,
                                     'nav_timeline_group_'.$this->groups->nickname);
            }
            
            if ($cnt > self::TOP_GROUPS) {
            	$this->out->menuItem(sprintf('javascript:SN.U.showMoreGroupMenuItems("%s")', 
                                             common_local_url('AtomPubMembershipFeed', array('profile' => $this->user->id))),
                                     _('More ▼'),
                                     _('More groups'),
                                     false,
                                     'nav_timeline_more_group_menu_items');
            }
            
            Event::handle('EndGroupsNav', array($this));
        }

        $this->out->elementEnd('ul');
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * User groups information
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/grouplist.php';

/**
 * User groups page
 *
 * Show the groups a user belongs to
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class UsergroupsAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Page title for first page of groups for a user.
            // TRANS: %s is a nickname.
            return sprintf(_('%s groups'), $this->getTarget()->getNickname());
        } else {
            // TRANS: Page title for all but the first page of groups for a user.
            // TRANS: %1$s is a nickname, %2$d is a page number.
            return sprintf(_('%1$s groups, page %2$d'),
                           $this->getTarget()->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->getTarget())) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscribed groups
                           // TRANS: of the logged in user's own profile.
                           _('These are the groups whose notices '.
                             'you listen to.'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all groups a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_('These are the groups whose '.
                                     'notices %s listens to.'),
                                   $this->target->getNickname()));
        }
    }

    function showContent()
    {
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->getTarget())) {
	  $notice =
          // TRANS: Page notice of user's groups page.
          // TRANS: %%%%action.groupsearch%%%% and %%%%action.newgroup%%%% are URLs. Do not change them.
          // TRANS: This message contains Markdown links in the form [link text](link).
          sprintf(_('Groups let you find and talk with ' .
		    'people of similar interests. ' .
		    'You can [search for groups](%%%%action.groups%%%%) in your instance or ' .
                    '[create a new group](%%%%action.newgroup%%%%). ' .
		    'You can also follow groups ' .
		    'from other GNU social instances: click on the remote button below ' .
		    'and copy the group\'s link. ' .
		    'You can find a list of GNU social groups [here](http://skilledtests.com/wiki/List_of_federated_GNU_social_groups)' .
		    ''));
	  $this->elementStart('div', 'instructions');
	  $this->raw(common_markup_to_html($notice));
	  $this->elementEnd('div');
	}

        if (Event::handle('StartShowUserGroupsContent', array($this))) {
            $offset = ($this->page-1) * GROUPS_PER_PAGE;
            $limit =  GROUPS_PER_PAGE + 1;

            $groups = $this->getTarget()->getGroups($offset, $limit);

            if ($groups instanceof User_group) {
                $gl = new GroupList($groups, $this->getTarget(), $this);
                $cnt = $gl->show();
		if (0 == $cnt) {
		  $this->showEmptyListMessage();
		} else {
		  $this->pagination($this->page > 1, $cnt > GROUPS_PER_PAGE,
				    $this->page, 'usergroups',
				    array('nickname' => $this->getTarget()->getNickname()));
		}
            }

            Event::handle('EndShowUserGroupsContent', array($this));
        }
    }

    function showEmptyListMessage()
    {
        // TRANS: Text on group page for a user that is not a member of any group.
        // TRANS: %s is a user nickname.
        $message = sprintf(_('%s is not a member of any group.'), $this->getTarget()->getNickname()) . ' ';
        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->scoped->sameAs($this->getTarget())) {
                // TRANS: Text on group page for a user that is not a member of any group. This message contains
                // TRANS: a Markdown link in the form [link text](link) and a variable that should not be changed.
	        $message = _('You are not member of any group yet. After you join a group ' .
			     'you can send messages to its members using the ' .
			     'syntax "!groupname".');
            }
	}
        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showProfileBlock()
    {
        $block = new AccountProfileBlock($this, $this->getTarget());
        $block->show();
    }
}

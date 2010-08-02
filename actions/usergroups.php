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

class UsergroupsAction extends OwnerDesignAction
{
    var $page = null;
    var $profile = null;

    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Message is used as a page title. %s is a nick name.
            return sprintf(_('%s groups'), $this->user->nickname);
        } else {
            // TRANS: Message is used as a page title. %1$s is a nick name, %2$d is a page number.
            return sprintf(_('%1$s groups, page %2$d'),
                           $this->user->nickname,
                           $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('usergroups', $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new SubGroupNav($this, $this->user);
        $nav->show();
    }

    function showContent()
    {
        $this->elementStart('p', array('id' => 'new_group'));
        $this->element('a', array('href' => common_local_url('newgroup'),
                                  'class' => 'more'),
                       _('Create a new group'));
        $this->elementEnd('p');

        $this->elementStart('p', array('id' => 'group_search'));
        $this->element('a', array('href' => common_local_url('groupsearch'),
                                  'class' => 'more'),
                       _('Search for more groups'));
        $this->elementEnd('p');

        if (Event::handle('StartShowUserGroupsContent', array($this))) {
            $offset = ($this->page-1) * GROUPS_PER_PAGE;
            $limit =  GROUPS_PER_PAGE + 1;

            $groups = $this->user->getGroups($offset, $limit);

            if ($groups) {
                $gl = new GroupList($groups, $this->user, $this);
                $cnt = $gl->show();
                if (0 == $cnt) {
                    $this->showEmptyListMessage();
                }
            }

            $this->pagination($this->page > 1, $cnt > GROUPS_PER_PAGE,
                              $this->page, 'usergroups',
                              array('nickname' => $this->user->nickname));

            Event::handle('EndShowUserGroupsContent', array($this));
        }
    }

    function showEmptyListMessage()
    {
        $message = sprintf(_('%s is not a member of any group.'), $this->user->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                $message .= _('Try [searching for groups](%%action.groupsearch%%) and joining them.');
            }
        }
        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}

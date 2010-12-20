<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of group members
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/lib/profilelist.php');
require_once INSTALLDIR.'/lib/publicgroupnav.php';

/**
 * List of group members
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class GroupmembersAction extends GroupDesignAction
{
    var $page = null;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('groupmembers', $args), 301);
            return false;
        }

        if (!$nickname) {
            $this->clientError(_('No nickname.'), 404);
            return false;
        }

        $local = Local_group::staticGet('nickname', $nickname);

        if (!$local) {
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $this->group = User_group::staticGet('id', $local->group_id);

        if (!$this->group) {
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title of the page showing group members.
            // TRANS: %s is the name of the group.
            return sprintf(_('%s group members'),
                           $this->group->nickname);
        } else {
            // TRANS: Title of the page showing group members.
            // TRANS: %1$s is the name of the group, %2$d is the page number of the members list.
            return sprintf(_('%1$s group members, page %2$d'),
                           $this->group->nickname,
                           $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showPageNotice()
    {
        $this->element('p', 'instructions',
                       _('A list of the users in this group.'));
    }

    function showLocalNav()
    {
        $nav = new GroupNav($this, $this->group);
        $nav->show();
    }

    function showContent()
    {
        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        $cnt = 0;

        $members = $this->group->getMembers($offset, $limit);

        if ($members) {
            $member_list = new GroupMemberList($members, $this->group, $this);
            $cnt = $member_list->show();
        }

        $members->free();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'groupmembers',
                          array('nickname' => $this->group->nickname));
    }
}

class GroupMemberList extends ProfileList
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function newListItem($profile)
    {
        return new GroupMemberListItem($profile, $this->group, $this->action);
    }
}

class GroupMemberListItem extends ProfileListItem
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function showFullName()
    {
        parent::showFullName();
        if ($this->profile->isAdmin($this->group)) {
            $this->out->text(' ');
            $this->out->element('span', 'role', _('Admin'));
        }
    }

    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            $this->showMakeAdminForm();
            $this->showGroupBlockForm();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showMakeAdminForm()
    {
        $user = common_current_user();

        if (!empty($user) &&
            $user->id != $this->profile->id &&
            ($user->isAdmin($this->group) || $user->hasRight(Right::MAKEGROUPADMIN)) &&
            !$this->profile->isAdmin($this->group)) {
            $this->out->elementStart('li', 'entity_make_admin');
            $maf = new MakeAdminForm($this->out, $this->profile, $this->group,
                                     $this->returnToArgs());
            $maf->show();
            $this->out->elementEnd('li');
        }

    }

    function showGroupBlockForm()
    {
        $user = common_current_user();

        if (!empty($user) && $user->id != $this->profile->id && $user->isAdmin($this->group)) {
            $this->out->elementStart('li', 'entity_block');
            $bf = new GroupBlockForm($this->out, $this->profile, $this->group,
                                     $this->returnToArgs());
            $bf->show();
            $this->out->elementEnd('li');
        }
    }

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }

    /**
     * Fetch necessary return-to arguments for the profile forms
     * to return to this list when they're done.
     * 
     * @return array
     */
    protected function returnToArgs()
    {
        $args = array('action' => 'groupmembers',
                      'nickname' => $this->group->nickname);
        $page = $this->out->arg('page');
        if ($page) {
            $args['param-page'] = $page;
        }
        return $args;
    }
}

/**
 * Form for blocking a user from a group
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      BlockForm
 */

class GroupBlockForm extends Form
{
    /**
     * Profile of user to block
     */

    var $profile = null;

    /**
     * Group to block the user from
     */

    var $group = null;

    /**
     * Return-to args
     */

    var $args = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param Profile       $profile profile of user to block
     * @param User_group    $group   group to block user from
     * @param array         $args    return-to args
     */

    function __construct($out=null, $profile=null, $group=null, $args=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
        $this->group   = $group;
        $this->args    = $args;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        // This should be unique for the page.
        return 'block-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_group_block';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('groupblock');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _('Block user from group'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('blockto-' . $this->profile->id,
                           $this->profile->id,
                           'blockto');
        $this->out->hidden('blockgroup-' . $this->group->id,
                           $this->group->id,
                           'blockgroup');
        if ($this->args) {
            foreach ($this->args as $k => $v) {
                $this->out->hidden('returnto-' . $k, $v);
            }
        }
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit(
            'submit',
            // TRANS: Button text for the form that will block a user from a group.
            _m('BUTTON','Block'),
            'submit',
            null,
            // TRANS: Submit button title.
            _m('TOOLTIP', 'Block this user'));
    }
}

/**
 * Form for making a user an admin for a group
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class MakeAdminForm extends Form
{
    /**
     * Profile of user to block
     */

    var $profile = null;

    /**
     * Group to block the user from
     */

    var $group = null;

    /**
     * Return-to args
     */

    var $args = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param Profile       $profile profile of user to block
     * @param User_group    $group   group to block user from
     * @param array         $args    return-to args
     */

    function __construct($out=null, $profile=null, $group=null, $args=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
        $this->group   = $group;
        $this->args    = $args;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        // This should be unique for the page.
        return 'makeadmin-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_make_admin';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('makeadmin', array('nickname' => $this->group->nickname));
    }

    /**
     * Legend of the Form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, _('Make user an admin of the group'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('profileid-' . $this->profile->id,
                           $this->profile->id,
                           'profileid');
        $this->out->hidden('groupid-' . $this->group->id,
                           $this->group->id,
                           'groupid');
        if ($this->args) {
            foreach ($this->args as $k => $v) {
                $this->out->hidden('returnto-' . $k, $v);
            }
        }
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit(
          'submit',
          // TRANS: Button text for the form that will make a user administrator.
          _m('BUTTON','Make Admin'),
          'submit',
          null,
          // TRANS: Submit button title.
          _m('TOOLTIP','Make this user an admin'));
    }
}

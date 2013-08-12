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

/**
 * List of profiles blocked from this group
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class BlockedfromgroupAction extends GroupAction
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
            common_redirect(common_local_url('blockedfromgroup', $args), 301);
            return false;
        }

        if (!$nickname) {
            // TRANS: Client error displayed when requesting a list of blocked users for a group without providing a group nickname.
            $this->clientError(_('No nickname.'), 404);
            return false;
        }

        $local = Local_group::staticGet('nickname', $nickname);

        if (!$local) {
            // TRANS: Client error displayed when requesting a list of blocked users for a non-local group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $this->group = User_group::staticGet('id', $local->group_id);

        if (!$this->group) {
            // TRANS: Client error displayed when requesting a list of blocked users for a non-existing group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title for first page with list of users blocked from a group.
            // TRANS: %s is a group nickname.
            return sprintf(_('%s blocked profiles'),
                           $this->group->nickname);
        } else {
            // TRANS: Title for any but the first page with list of users blocked from a group.
            // TRANS: %1$s is a group nickname, %2$d is a page number.
            return sprintf(_('%1$s blocked profiles, page %2$d'),
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
                       // TRANS: Instructions for list of users blocked from a group.
                       _('A list of the users blocked from joining this group.'));
    }

    function showContent()
    {
        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        $cnt = 0;

        $blocked = $this->group->getBlocked($offset, $limit);

        if ($blocked) {
            $blocked_list = new GroupBlockList($blocked, $this->group, $this);
            $cnt = $blocked_list->show();
        }

        $blocked->free();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'blockedfromgroup',
                          array('nickname' => $this->group->nickname));
    }
}

class GroupBlockList extends ProfileList
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function newListItem($profile)
    {
        return new GroupBlockListItem($profile, $this->group, $this->action);
    }
}

class GroupBlockListItem extends ProfileListItem
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function showActions()
    {
        $this->startActions();
        $this->showGroupUnblockForm();
        $this->endActions();
    }

    function showGroupUnblockForm()
    {
        $user = common_current_user();

        if (!empty($user) && $user->id != $this->profile->id && $user->isAdmin($this->group)) {
            $this->out->elementStart('li', 'entity_block');
            $bf = new GroupUnblockForm($this->out, $this->profile, $this->group,
                                       array('action' => 'blockedfromgroup',
                                             'nickname' => $this->group->nickname));
            $bf->show();
            $this->out->elementEnd('li');
        }
    }
}

/**
 * Form for unblocking a user from a group
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnblockForm
 */
class GroupUnblockForm extends Form
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
        return 'unblock-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_group_unblock';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('groupunblock');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend for unblocking a user from a group.
        $this->out->element('legend', null, _('Unblock user from group'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('unblockto-' . $this->profile->id,
                           $this->profile->id,
                           'unblockto');
        $this->out->hidden('unblockgroup-' . $this->group->id,
                           $this->group->id,
                           'unblockgroup');
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
        $this->out->submit('submit',
                           // TRANS: Button text for unblocking a user from a group.
                           _m('BUTTON','Unblock'),
                           'submit',
                           null,
                           // TRANS: Tooltip for button for unblocking a user from a group.
                           _('Unblock this user'));
    }
}

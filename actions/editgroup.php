<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Edit an existing group
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Add a new group
 *
 * This is the form for adding a new group
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class EditgroupAction extends GroupAction
{
    var $msg;

    function title()
    {
        // TRANS: Title for form to edit a group. %s is a group nickname.
        return sprintf(_('Edit %s group'), $this->group->nickname);
    }

    /**
     * Prepare to run
     */

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to edit a group while not logged in.
            $this->clientError(_('You must be logged in to create a group.'));
        }

        $nickname_arg = $this->trimmed('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            common_redirect(common_local_url('editgroup', $args), 301);
        }

        if (!$nickname) {
            // TRANS: Client error displayed trying to edit a group while not proving a nickname for the group to edit.
            $this->clientError(_('No nickname.'), 404);
        }

        $groupid = $this->trimmed('groupid');

        if ($groupid) {
            $this->group = User_group::getKV('id', $groupid);
        } else {
            $local = Local_group::getKV('nickname', $nickname);
            if ($local) {
                $this->group = User_group::getKV('id', $local->group_id);
            }
        }

        if (!$this->group) {
            // TRANS: Client error displayed trying to edit a non-existing group.
            $this->clientError(_('No such group.'), 404);
        }

        $cur = common_current_user();

        if (!$cur->isAdmin($this->group)) {
            // TRANS: Client error displayed trying to edit a group while not being a group admin.
            $this->clientError(_('You must be an admin to edit the group.'), 403);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the group.
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->trySave();
        } else {
            $this->showForm();
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showContent()
    {
        $form = new GroupEditForm($this, $this->group);
        $form->show();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           // TRANS: Form instructions for group edit form.
                           _('Use this form to edit the group.'));
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('fullname');
    }

    function trySave()
    {
        $cur = common_current_user();
        if (!$cur->isAdmin($this->group)) {
            // TRANS: Client error displayed trying to edit a group while not being a group admin.
            $this->clientError(_('You must be an admin to edit the group.'), 403);
        }

        if (Event::handle('StartGroupSaveForm', array($this))) {

            // $nickname will only be set if this changenick value is true.
            if (common_config('profile', 'changenick') == true) {
                try {
                    $nickname = Nickname::normalize($this->trimmed('newnickname'), true);
                } catch (NicknameTakenException $e) {
                    // Abort only if the nickname is occupied by _another_ group
                    if ($e->profile->id != $this->group->profile_id) {
                        $this->showForm($e->getMessage());
                        return;
                    }
                    $nickname = Nickname::normalize($this->trimmed('newnickname')); // without in-use check this time
                } catch (NicknameException $e) {
                    $this->showForm($e->getMessage());
                    return;
                }
            }

            $fullname    = $this->trimmed('fullname');
            $homepage    = $this->trimmed('homepage');
            $description = $this->trimmed('description');
            $location    = $this->trimmed('location');
            $aliasstring = $this->trimmed('aliases');
            $private     = $this->boolean('private');

            if ($private) {
                $force_scope = 1;
                $join_policy = User_group::JOIN_POLICY_MODERATE;
            } else {
                $force_scope = 0;
                $join_policy = User_group::JOIN_POLICY_OPEN;
            }

            if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !common_valid_http_url($homepage)) {
                // TRANS: Group edit form validation error.
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                // TRANS: Group edit form validation error.
                $this->showForm(_('Full name is too long (maximum 255 characters).'));
                return;
            } else if (User_group::descriptionTooLong($description)) {
                $this->showForm(sprintf(
                                    // TRANS: Group edit form validation error.
                                    _m('Description is too long (maximum %d character).',
                                       'Description is too long (maximum %d characters).',
                                       User_group::maxDescription()),
                                    User_group::maxDescription()));
                return;
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                // TRANS: Group edit form validation error.
                $this->showForm(_('Location is too long (maximum 255 characters).'));
                return;
            }

            if (!empty($aliasstring)) {
                $aliases = array_map(array('Nickname', 'normalize'),
                                     array_unique(preg_split('/[\s,]+/', $aliasstring)));
            } else {
                $aliases = array();
            }

            if (count($aliases) > common_config('group', 'maxaliases')) {
                // TRANS: Group edit form validation error.
                // TRANS: %d is the maximum number of allowed aliases.
                $this->showForm(sprintf(_m('Too many aliases! Maximum %d allowed.',
                                           'Too many aliases! Maximum %d allowed.',
                                           common_config('group', 'maxaliases')),
                                        common_config('group', 'maxaliases')));
                return;
            }

            $this->group->query('BEGIN');

            $orig = clone($this->group);

            if (common_config('profile', 'changenick') == true && $this->group->nickname !== $nickname) {
                assert(Nickname::normalize($nickname)===$nickname);
                common_debug("Changing group nickname from '{$profile->nickname}' to '{$nickname}'.");
                $this->group->nickname = $nickname;
                $this->group->mainpage = common_local_url('showgroup', array('nickname' => $this->group->nickname));
            }
            $this->group->fullname    = $fullname;
            $this->group->homepage    = $homepage;
            $this->group->description = $description;
            $this->group->location    = $location;
            $this->group->join_policy = $join_policy;
            $this->group->force_scope = $force_scope;

            $result = $this->group->update($orig);

            if ($result === false) {
                common_log_db_error($this->group, 'UPDATE', __FILE__);
                // TRANS: Server error displayed when editing a group fails.
                $this->serverError(_('Could not update group.'));
            }

            $result = $this->group->setAliases($aliases);

            if (!$result) {
                // TRANS: Server error displayed when group aliases could not be added.
                $this->serverError(_('Could not create aliases.'));
            }

            $this->group->query('COMMIT');

            Event::handle('EndGroupSaveForm', array($this));
        }

        if ($this->group->nickname != $orig->nickname) {
            common_redirect(common_local_url('editgroup', array('nickname' => $this->group->nickname)), 303);
        } else {
            // TRANS: Group edit form success message.
            $this->showForm(_('Options saved.'));
        }
    }
}

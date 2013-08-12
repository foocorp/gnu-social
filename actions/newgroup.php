<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Add a new group
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
 * @copyright 2008-2009 StatusNet, Inc.
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
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class NewgroupAction extends Action
{
    var $msg;

    function title()
    {
        // TRANS: Title for form to create a group.
        return _('New group');
    }

    /**
     * Prepare to run
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to create a group while not logged in.
            $this->clientError(_('You must be logged in to create a group.'));
            return false;
        }

        $user = common_current_user();
        $profile = $user->getProfile();
        if (!$profile->hasRight(Right::CREATEGROUP)) {
            // TRANS: Client exception thrown when a user tries to create a group while banned.
            throw new ClientException(_('You are not allowed to create groups on this site.'), 403);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the group.
     *
     * @param array $args unused
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
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
        $form = new GroupEditForm($this);
        $form->show();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           // TRANS: Form instructions for group create form.
                           _('Use this form to create a new group.'));
        }
    }

    function trySave()
    {
        if (Event::handle('StartGroupSaveForm', array($this))) {
            try {
                $nickname = Nickname::normalize($this->trimmed('newnickname'));
            } catch (NicknameException $e) {
                $this->showForm($e->getMessage());
            }
            $fullname    = $this->trimmed('fullname');
            $homepage    = $this->trimmed('homepage');
            $description = $this->trimmed('description');
            $location    = $this->trimmed('location');
            $private     = $this->boolean('private');
            $aliasstring = $this->trimmed('aliases');

            if ($this->nicknameExists($nickname)) {
                // TRANS: Group create form validation error.
                $this->showForm(_('Nickname already in use. Try another one.'));
                return;
            } else if (!User_group::allowedNickname($nickname)) {
                // TRANS: Group create form validation error.
                $this->showForm(_('Not a valid nickname.'));
                return;
            } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !Validate::uri($homepage,
                                      array('allowed_schemes' =>
                                            array('http', 'https')))) {
                // TRANS: Group create form validation error.
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                // TRANS: Group create form validation error.
                $this->showForm(_('Full name is too long (maximum 255 characters).'));
                return;
            } else if (User_group::descriptionTooLong($description)) {
                // TRANS: Group create form validation error.
                // TRANS: %d is the maximum number of allowed characters.
                $this->showForm(sprintf(_m('Description is too long (maximum %d character).',
                                           'Description is too long (maximum %d characters).',
                                           User_group::maxDescription()),
                                        User_group::maxDescription()));
                return;
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                // TRANS: Group create form validation error.
                $this->showForm(_('Location is too long (maximum 255 characters).'));
                return;
            }

            if (!empty($aliasstring)) {
                $aliases = array_map('common_canonical_nickname', array_unique(preg_split('/[\s,]+/', $aliasstring)));
            } else {
                $aliases = array();
            }

            if (count($aliases) > common_config('group', 'maxaliases')) {
                // TRANS: Group create form validation error.
                // TRANS: %d is the maximum number of allowed aliases.
                $this->showForm(sprintf(_m('Too many aliases! Maximum %d allowed.',
                                           'Too many aliases! Maximum %d allowed.',
                                           common_config('group', 'maxaliases')),
                                        common_config('group', 'maxaliases')));
                return;
            }

            foreach ($aliases as $alias) {
                if (!Nickname::isValid($alias)) {
                    // TRANS: Group create form validation error.
                    // TRANS: %s is the invalid alias.
                    $this->showForm(sprintf(_('Invalid alias: "%s"'), $alias));
                    return;
                }
                if ($this->nicknameExists($alias)) {
                    // TRANS: Group create form validation error. %s is the already used alias.
                    $this->showForm(sprintf(_('Alias "%s" already in use. Try another one.'),
                                            $alias));
                    return;
                }
                // XXX assumes alphanum nicknames
                if (strcmp($alias, $nickname) == 0) {
                    // TRANS: Group create form validation error.
                    $this->showForm(_('Alias cannot be the same as nickname.'));
                    return;
                }
            }

            if ($private) {
                $force_scope = 1;
                $join_policy = User_group::JOIN_POLICY_MODERATE;
            } else {
                $force_scope = 0;
                $join_policy = User_group::JOIN_POLICY_OPEN;
            }

            $cur = common_current_user();

            // Checked in prepare() above

            assert(!is_null($cur));

            $group = User_group::register(array('nickname' => $nickname,
                                                'fullname' => $fullname,
                                                'homepage' => $homepage,
                                                'description' => $description,
                                                'location' => $location,
                                                'aliases'  => $aliases,
                                                'userid'   => $cur->id,
                                                'join_policy' => $join_policy,
                                                'force_scope' => $force_scope,
                                                'local'    => true));

            $this->group = $group;

            Event::handle('EndGroupSaveForm', array($this));

            common_redirect($group->homeUrl(), 303);
        }
    }

    function nicknameExists($nickname)
    {
        $local = Local_group::staticGet('nickname', $nickname);

        if (!empty($local)) {
            return true;
        }

        $alias = Group_alias::staticGet('alias', $nickname);

        if (!empty($alias)) {
            return true;
        }

        return false;
    }
}

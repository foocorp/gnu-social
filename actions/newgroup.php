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
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
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
class NewgroupAction extends FormAction
{
    function title()
    {
        // TRANS: Title for form to create a group.
        return _('New group');
    }

    /**
     * Prepare to run
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        // $this->scoped is the current user profile
        if (!$this->scoped->hasRight(Right::CREATEGROUP)) {
            // TRANS: Client exception thrown when a user tries to create a group while banned.
            $this->clientError(_('You are not allowed to create groups on this site.'), 403);
        }

        return true;
    }

    public function showContent()
    {
        $form = new GroupEditForm($this);
        $form->show();
    }

    public function showInstructions()
    {
        $this->element('p', 'instructions',
                       // TRANS: Form instructions for group create form.
                       _('Use this form to create a new group.'));
    }

    protected function handlePost()
    {
        parent::handlePost();

        if (Event::handle('StartGroupSaveForm', array($this))) {
            $nickname = Nickname::normalize($this->trimmed('newnickname'), true);

            $fullname    = $this->trimmed('fullname');
            $homepage    = $this->trimmed('homepage');
            $description = $this->trimmed('description');
            $location    = $this->trimmed('location');
            $private     = $this->boolean('private');
            $aliasstring = $this->trimmed('aliases');

            if (!is_null($homepage) && (strlen($homepage) > 0) &&
                       !common_valid_http_url($homepage)) {
                // TRANS: Group create form validation error.
                throw new ClientException(_('Homepage is not a valid URL.'));
            } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
                // TRANS: Group create form validation error.
                throw new ClientException(_('Full name is too long (maximum 255 characters).'));
            } else if (User_group::descriptionTooLong($description)) {
                // TRANS: Group create form validation error.
                // TRANS: %d is the maximum number of allowed characters.
                throw new ClientException(sprintf(_m('Description is too long (maximum %d character).',
                                           'Description is too long (maximum %d characters).',
                                           User_group::maxDescription()),
                                        User_group::maxDescription()));
            } else if (!is_null($location) && mb_strlen($location) > 255) {
                // TRANS: Group create form validation error.
                throw new ClientException(_('Location is too long (maximum 255 characters).'));
            }

            if (!empty($aliasstring)) {
                $aliases = array_map(array('Nickname', 'normalize'), array_unique(preg_split('/[\s,]+/', $aliasstring)));
            } else {
                $aliases = array();
            }

            if (count($aliases) > common_config('group', 'maxaliases')) {
                // TRANS: Group create form validation error.
                // TRANS: %d is the maximum number of allowed aliases.
                throw new ClientException(sprintf(_m('Too many aliases! Maximum %d allowed.',
                                           'Too many aliases! Maximum %d allowed.',
                                           common_config('group', 'maxaliases')),
                                        common_config('group', 'maxaliases')));
                return;
            }

            if ($private) {
                $force_scope = 1;
                $join_policy = User_group::JOIN_POLICY_MODERATE;
            } else {
                $force_scope = 0;
                $join_policy = User_group::JOIN_POLICY_OPEN;
            }

            // This is set up in parent->prepare and checked in self->prepare
            assert(!is_null($this->scoped));

            $group = User_group::register(array('nickname' => $nickname,
                                                'fullname' => $fullname,
                                                'homepage' => $homepage,
                                                'description' => $description,
                                                'location' => $location,
                                                'aliases'  => $aliases,
                                                'userid'   => $this->scoped->id,
                                                'join_policy' => $join_policy,
                                                'force_scope' => $force_scope,
                                                'local'    => true));

            $this->group = $group;

            Event::handle('EndGroupSaveForm', array($this));

            common_redirect($group->homeUrl(), 303);
        }
    }
}

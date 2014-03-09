<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action class to grant user roles.
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Assign role to user.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class GrantRoleAction extends ProfileFormAction
{
    /**
     * Check parameters
     *
     * @param array $args action arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        $this->role = $this->arg('role');
        if (!Profile_role::isValid($this->role)) {
            // TRANS: Client error displayed when trying to assign an invalid role to a user.
            $this->clientError(_('Invalid role.'));
        }
        if (!Profile_role::isSettable($this->role)) {
            // TRANS: Client error displayed when trying to assign an reserved role to a user.
            $this->clientError(_('This role is reserved and cannot be set.'));
        }

        $cur = common_current_user();

        assert(!empty($cur)); // checked by parent

        if (!$cur->hasRight(Right::GRANTROLE)) {
            // TRANS: Client error displayed when trying to assign a role to a user while not being allowed to set roles.
            $this->clientError(_('You cannot grant user roles on this site.'));
        }

        assert(!empty($this->profile)); // checked by parent

        if ($this->profile->hasRole($this->role)) {
            // TRANS: Client error displayed when trying to assign a role to a user that already has that role.
            $this->clientError(_('User already has this role.'));
        }

        return true;
    }

    /**
     * Sandbox a user.
     *
     * @return void
     */
    function handlePost()
    {
        $this->profile->grantRole($this->role);
    }
}

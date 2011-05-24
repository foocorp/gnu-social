<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for granting a role
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
 * @category  Form
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>, Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Form for sandboxing a user
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnSandboxForm
 */
class GrantRoleForm extends ProfileActionForm
{
    function __construct($role, $label, $writer, $profile, $r2args)
    {
        parent::__construct($writer, $profile, $r2args);
        $this->role = $role;
        $this->label = $label;
    }

    /**
     * Action this form provides
     *
     * @return string Name of the action, lowercased.
     */
    function target()
    {
        return 'grantrole';
    }

    /**
     * Title of the form
     *
     * @return string Title of the form, internationalized
     */
    function title()
    {
        return $this->label;
    }

    function formData()
    {
        parent::formData();
        $this->out->hidden('role', $this->role);
    }

    /**
     * Description of the form
     *
     * @return string description of the form, internationalized
     */
    function description()
    {
        // TRANS: Description on form for granting a role.
        return sprintf(_('Grant this user the "%s" role'), $this->label);
    }
}

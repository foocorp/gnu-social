<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for an exception when the group profile is missing
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
 * @category  Exception
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Class for an exception when the group profile is missing
 *
 * @category Exception
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class GroupNoProfileException extends NoProfileException
{
    protected $group = null;

    /**
     * constructor
     *
     * @param User_group $user User_group that's missing a profile
     */

    public function __construct(User_group $group)
    {
        $this->group = $group;

        // TRANS: Exception text shown when no profile can be found for a group.
        // TRANS: %1$s is a group nickname, $2$d is a group profile_id (number).
        $message = sprintf(_('Group "%1$s" (%2$d) has no profile record.'),
                           $group->nickname, $group->getID());

        parent::__construct($group->profile_id, $message);
    }

    /**
     * Accessor for user
     *
     * @return User_group the group that triggered this exception
     */

    public function getGroup()
    {
        return $this->group;
    }
}

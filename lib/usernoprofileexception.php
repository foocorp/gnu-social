<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * class for an exception when the user profile is missing
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class for an exception when the user profile is missing
 *
 * @category Exception
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class UserNoProfileException extends ServerException
{
    var $user = null;

    /**
     * constructor
     *
     * @param User $user User that's missing a profile
     */

    public function __construct($user)
    {
        $this->user = $user;

        // TRANS: Exception text shown when no profile can be found for a user.
        // TRANS: %1$s is a user nickname, $2$d is a user ID (number).
        $message = sprintf(_('User %1$s (%2$d) has no profile record.'),
                           $user->nickname, $user->id);

        parent::__construct($message);
    }

    /**
     * Accessor for user
     *
     * @return User the user that triggered this exception
     */

    public function getUser()
    {
        return $this->user;
    }
}

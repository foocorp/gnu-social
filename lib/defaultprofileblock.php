<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Default profile block to show current user's info
 * 
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Default profile block
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class DefaultProfileBlock extends AccountProfileBlock
{
    function __construct($out)
    {
        $user = common_current_user();
        if (empty($user)) {
            throw new Exception("DefaultProfileBlock with no user.");
        }
        parent::__construct($out, $user->getProfile());
    }

    function avatarSize()
    {
        return AVATAR_STREAM_SIZE;
    }

    function avatar()
    {
        $avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);
        if (empty($avatar)) {
            $avatar = $this->profile->getAvatar(73);
        }
        return (!empty($avatar)) ? 
            $avatar->displayUrl() : 
            Avatar::defaultImage(AVATAR_STREAM_SIZE);
    }

    function location()
    {
        return null;
    }

    function homepage()
    {
        return null;
    }

    function description()
    {
        return null;
    }

    function otherProfiles()
    {
        return array();
    }
}
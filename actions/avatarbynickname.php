<?php
/**
 * Retrieve user avatar by nickname action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Retrieve user avatar by nickname action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class AvatarbynicknameAction extends Action
{
    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return boolean false if nickname or user isn't found
     */
    function handle($args)
    {
        parent::handle($args);
        $nickname = $this->trimmed('nickname');
        if (!$nickname) {
            // TRANS: Client error displayed trying to get an avatar without providing a nickname.
            $this->clientError(_('No nickname.'));
            return;
        }
        $size = $this->trimmed('size');
        if (!$size) {
            // TRANS: Client error displayed trying to get an avatar without providing an avatar size.
            $this->clientError(_('No size.'));
            return;
        }
        $size = strtolower($size);
        if (!in_array($size, array('original', '96', '48', '24'))) {
            // TRANS: Client error displayed trying to get an avatar providing an invalid avatar size.
            $this->clientError(_('Invalid size.'));
            return;
        }

        $user = User::getKV('nickname', $nickname);
        if (!$user) {
            // TRANS: Client error displayed trying to get an avatar for a non-existing user.
            $this->clientError(_('No such user.'));
            return;
        }
        $profile = $user->getProfile();
        if (!$profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->clientError(_('User has no profile.'));
            return;
        }
        if ($size == 'original') {
            $avatar = $profile->getOriginal();
        } else {
            $avatar = $profile->getAvatar($size+0);
        }

        if ($avatar) {
            $url = $avatar->url;
        } else {
            if ($size == 'original') {
                $url = Avatar::defaultImage(AVATAR_PROFILE_SIZE);
            } else {
                $url = Avatar::defaultImage($size+0);
            }
        }
        common_redirect($url, 302);
    }

    function isReadOnly($args)
    {
        return true;
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009,2011 StatusNet, Inc.
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

/**
 * @package GravatarPlugin
 * @maintainer Eric Helgeson <erichelgeson@gmail.com>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

class GravatarPlugin extends Plugin
{
    function onEndProfileGetAvatar($profile, $size, &$avatar)
    {
        if (empty($avatar)) {
            try {
                $user = $profile->getUser();
                if (!empty($user->email)) {
                    // Fake one!
                    $avatar = new Avatar();
                    $avatar->width = $avatar->height = $size;
                    $avatar->url = $this->gravatar_url($user->email, $size);
                    return false;
                }
            } catch (NoSuchUserException $e) {
                return true;
            }
        }

        return true;
    }

    function gravatar_url($email, $size)
    {
        $url = "https://secure.gravatar.com/avatar.php?gravatar_id=".
                md5(strtolower($email)).
                "&default=".urlencode(Avatar::defaultImage($size)).
                "&size=".$size;
            return $url;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Gravatar',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Eric Helgeson, Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Gravatar',
                            'rawdescription' =>
                            // TRANS: Plugin decsription.
                            _m('The Gravatar plugin allows users to use their <a href="http://www.gravatar.com/">Gravatar</a> with StatusNet.'));

        return true;
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */
class UserxrdAction extends XrdAction
{
    function prepare($args)
    {
        parent::prepare($args);
        global $config;

        $this->uri = $this->trimmed('uri');
        $this->uri = self::normalize($this->uri);

        if (self::isWebfinger($this->uri)) {
            $parts = explode('@', substr(urldecode($this->uri), 5));
            if (count($parts) == 2) {
                list($nick, $domain) = $parts;
                // @fixme confirm the domain too
                // @fixme if domain checking is added, ensure that it will not
                //        cause problems with sites that have changed domains!
                $nick = common_canonical_nickname($nick);
                $this->user = User::staticGet('nickname', $nick);
            }
        } else {
            $this->user = User::staticGet('uri', $this->uri);
            if (empty($this->user)) {
                // try and get it by profile url
                $profile = Profile::staticGet('profileurl', $this->uri);
                if (!empty($profile)) {
                    $this->user = User::staticGet('id', $profile->id);
                }
            }
        }

        if (!$this->user) {
            // TRANS: Client error displayed when user not found for an action.
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        return true;
    }
}

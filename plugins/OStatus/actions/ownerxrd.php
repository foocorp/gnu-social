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

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class OwnerxrdAction extends XrdAction
{

    public $uri;

    function prepare($args)
    {
        $this->user = User::siteOwner();

        if (!$this->user) {
            $this->clientError(_m('No such user.'), 404);
            return false;
        }

        $nick = common_canonical_nickname($this->user->nickname);
        $acct = 'acct:' . $nick . '@' . common_config('site', 'server');

        $this->xrd = new XRD();

        // Check to see if a $config['webfinger']['owner'] has been set
        if ($owner = common_config('webfinger', 'owner')) {
            $this->xrd->subject = Discovery::normalize($owner);
            $this->xrd->alias[] = $acct;
        } else {
            $this->xrd->subject = $acct;
        }

        return true;
    }
}

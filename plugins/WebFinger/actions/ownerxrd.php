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
 * @package WebFingerPlugin
 * @author James Walker <james@status.net>
 * @author Mikael Nordfeldth <mmn@hethane.se>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class OwnerxrdAction extends WebfingerAction
{
    protected $defaultformat = 'xml';

    protected function prepare(array $args=array())
    {
        $user = User::siteOwner();

        $nick = common_canonical_nickname($user->nickname);
        $args['resource'] = 'acct:' . $nick . '@' . common_config('site', 'server');

        // We have now set $args['resource'] to the configured value, since 
        // only this local site configuration knows who the owner is!
        parent::prepare($args);

        return true;
    }

    protected function setXRD()
    {
        parent::setXRD();

        // Check to see if a $config['webfinger']['owner'] has been set
        // and then make sure 'subject' is set to that primary identity.
        if ($owner = common_config('webfinger', 'owner')) {
            $this->xrd->aliases[] = $this->xrd->subject;
            $this->xrd->subject = Discovery::normalize($owner);
        } else {
            $this->xrd->subject = $this->resource;
        }
    }
}

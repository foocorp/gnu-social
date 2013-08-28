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

class XrdAction extends Action
{
    public $uri;

    public $user;

    public $xrd;

    function handle()
    {
        $nick    = $this->user->nickname;
        $profile = $this->user->getProfile();

        if (empty($this->xrd)) {
            $xrd = new XRD();
        } else {
            $xrd = $this->xrd;
        }

        if (empty($xrd->subject)) {
            $xrd->subject = Discovery::normalize($this->uri);
        }

        // Possible aliases for the user

        $uris = array($this->user->uri, $profile->profileurl);

        // FIXME: Webfinger generation code should live somewhere on its own

        $path = common_config('site', 'path');

        if (empty($path)) {
            $uris[] = sprintf('acct:%s@%s', $nick, common_config('site', 'server'));
        }

        foreach ($uris as $uri) {
            if ($uri != $xrd->subject) {
                $xrd->alias[] = $uri;
            }
        }

        $xrd->links[] = array('rel' => Discovery::PROFILEPAGE,
                              'type' => 'text/html',
                              'href' => $profile->profileurl);

        $xrd->links[] = array('rel' => Discovery::UPDATESFROM,
                              'href' => common_local_url('ApiTimelineUser',
                                                         array('id' => $this->user->id,
                                                               'format' => 'atom')),
                              'type' => 'application/atom+xml');

        // XFN
        $xrd->links[] = array('rel' => 'http://gmpg.org/xfn/11',
                              'type' => 'text/html',
                              'href' => $profile->profileurl);
        // FOAF
        $xrd->links[] = array('rel' => 'describedby',
                              'type' => 'application/rdf+xml',
                              'href' => common_local_url('foaf',
                                                         array('nickname' => $nick)));

        // Salmon
        $salmon_url = common_local_url('usersalmon',
                                       array('id' => $this->user->id));

        $xrd->links[] = array('rel' => Salmon::REL_SALMON,
                              'href' => $salmon_url);
        // XXX : Deprecated - to be removed.
        $xrd->links[] = array('rel' => Salmon::NS_REPLIES,
                              'href' => $salmon_url);

        $xrd->links[] = array('rel' => Salmon::NS_MENTIONS,
                              'href' => $salmon_url);

        // Get this user's keypair
        $magickey = Magicsig::getKV('user_id', $this->user->id);
        if (!$magickey) {
            // No keypair yet, let's generate one.
            $magickey = new Magicsig();
            $magickey->generate($this->user->id);
        }

        $xrd->links[] = array('rel' => Magicsig::PUBLICKEYREL,
                              'href' => 'data:application/magic-public-key,'. $magickey->toString(false));

        // TODO - finalize where the redirect should go on the publisher
        $url = common_local_url('ostatussub') . '?profile={uri}';
        $xrd->links[] = array('rel' => 'http://ostatus.org/schema/1.0/subscribe',
                              'template' => $url );

        $url = common_local_url('tagprofile') . '?uri={uri}';
        $xrd->links[] = array('rel' => 'http://ostatus.org/schema/1.0/tag',
                              'template' => $url );

        header('Content-type: application/xrd+xml');
        print $xrd->toXML();
    }
}

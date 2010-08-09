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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class XrdAction extends Action
{

    public $uri;
    
    public $user;

    public $xrd;
    
    function handle()
    {
        $nick =  $this->user->nickname;

        if (empty($this->xrd)) {
            $xrd = new XRD();
        } else {
            $xrd = $this->xrd;
        }

        if (empty($xrd->subject)) {
            $xrd->subject = Discovery::normalize($this->uri);
        }
        $xrd->alias[] = $this->user->uri;
        $xrd->links[] = array('rel' => Discovery::PROFILEPAGE,
                              'type' => 'text/html',
                              'href' => $this->user->uri);

        $xrd->links[] = array('rel' => Discovery::UPDATESFROM,
                              'href' => common_local_url('ApiTimelineUser',
                                                         array('id' => $this->user->id,
                                                               'format' => 'atom')),
                              'type' => 'application/atom+xml');

        // hCard
        $xrd->links[] = array('rel' => Discovery::HCARD,
                              'type' => 'text/html',
                              'href' => common_local_url('hcard', array('nickname' => $nick)));

        // XFN
        $xrd->links[] = array('rel' => 'http://gmpg.org/xfn/11',
                              'type' => 'text/html',
                              'href' => $this->user->uri);
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
        $magickey = Magicsig::staticGet('user_id', $this->user->id);
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

        header('Content-type: application/xrd+xml');
        print $xrd->toXML();
    }

}

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
    const PROFILEPAGE = 'http://webfinger.net/rel/profile-page';
    const UPDATESFROM = 'http://schemas.google.com/g/2010#updates-from';
    const HCARD = 'http://microformats.org/profile/hcard';

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
            $xrd->subject = self::normalize($this->uri);
        }

        if (Event::handle('StartXrdActionAliases', array(&$xrd, $this->user))) {

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

            Event::handle('EndXrdActionAliases', array(&$xrd, $this->user));
        }

        if (Event::handle('StartXrdActionLinks', array(&$xrd, $this->user))) {

            $xrd->links[] = array('rel' => self::PROFILEPAGE,
                                  'type' => 'text/html',
                                  'href' => $profile->profileurl);

            // XFN
            $xrd->links[] = array('rel' => 'http://gmpg.org/xfn/11',
                                  'type' => 'text/html',
                                  'href' => $profile->profileurl);
            // FOAF
            $xrd->links[] = array('rel' => 'describedby',
                                  'type' => 'application/rdf+xml',
                                  'href' => common_local_url('foaf',
                                                             array('nickname' => $nick)));

            $xrd->links[] = array('rel' => 'http://apinamespace.org/atom',
                                  'type' => 'application/atomsvc+xml',
                                  'href' => common_local_url('ApiAtomService', array('id' => $nick)),
                                  'property' => array(array('type' => 'http://apinamespace.org/atom/username',
                                                            'value' => $nick)));

            if (common_config('site', 'fancy')) {
                $apiRoot = common_path('api/', true);
            } else {
                $apiRoot = common_path('index.php/api/', true);
            }

            $xrd->links[] = array('rel' => 'http://apinamespace.org/twitter',
                                  'href' => $apiRoot,
                                  'property' => array(array('type' => 'http://apinamespace.org/twitter/username',
                                                            'value' => $nick)));

            Event::handle('EndXrdActionLinks', array(&$xrd, $this->user));
        }

        if (common_config('discovery', 'cors')) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Content-type: application/xrd+xml');

        print $xrd->toXML();
    }

    /**
     * Given a "user id" make sure it's normalized to either a webfinger
     * acct: uri or a profile HTTP URL.
     */

    public static function normalize($user_id)
    {
        if (substr($user_id, 0, 5) == 'http:' ||
            substr($user_id, 0, 6) == 'https:' ||
            substr($user_id, 0, 5) == 'acct:') {
            return $user_id;
        }

        if (strpos($user_id, '@') !== FALSE) {
            return 'acct:' . $user_id;
        }

        return 'http://' . $user_id;
    }

    public static function isWebfinger($user_id)
    {
        $uri = self::normalize($user_id);

        return (substr($uri, 0, 5) == 'acct:');
    }

    /**
     * Is this action read-only?
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}

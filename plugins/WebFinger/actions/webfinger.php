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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package WebFingerPlugin
 * @author James Walker <james@status.net>
 * @author Mikael Nordfeldth <mmn@hethane.se>
 */
class WebfingerAction extends XrdAction
{
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        // throws exception if resource is empty
        $this->resource = Discovery::normalize($this->trimmed('resource'));

        if (Discovery::isAcct($this->resource)) {
            $parts = explode('@', substr(urldecode($this->resource), 5));
            if (count($parts) == 2) {
                list($nick, $domain) = $parts;
                if ($domain === common_config('site', 'server')) {
                    $nick = common_canonical_nickname($nick);
                    $user = User::getKV('nickname', $nick);
                    if (!($user instanceof User)) {
                        throw new NoSuchUserException(array('nickname'=>$nick));
                    }
                    $this->target = $user->getProfile();
                } else {
                    throw new Exception(_('Remote profiles not supported via WebFinger yet.'));
                }
            }
        } else {
            $user = User::getKV('uri', $this->resource);
            if ($user instanceof User) {
                $this->target = $user->getProfile();
            } else {
                // try and get it by profile url
                $this->target = Profile::getKV('profileurl', $this->resource);
            }
        }

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed when user not found for an action.
            $this->clientError(_('No such user: ') . var_export($this->resource,true), 404);
        }

        return true;
    }

    protected function setXRD()
    {
        if (empty($this->target)) {
            throw new Exception(_('Target not set for resource descriptor'));
        }

        // $this->target set in a _child_ class prepare()
        $nick = $this->target->nickname;

        $this->xrd->subject = $this->resource;

        if (Event::handle('StartXrdActionAliases', array($this->xrd, $this->target))) {
            $uris = WebFinger::getIdentities($this->target);
            foreach ($uris as $uri) {
                if ($uri != $this->xrd->subject && !in_array($uri, $this->xrd->aliases)) {
                    $this->xrd->aliases[] = $uri;
                }
            }
            Event::handle('EndXrdActionAliases', array($this->xrd, $this->target));
        }

        if (Event::handle('StartXrdActionLinks', array($this->xrd, $this->target))) {

            $this->xrd->links[] = new XML_XRD_Element_Link(WebFinger::PROFILEPAGE,
                                        $this->target->getUrl(), 'text/html');

            // XFN
            $this->xrd->links[] = new XML_XRD_Element_Link('http://gmpg.org/xfn/11',
                                        $this->target->getUrl(), 'text/html');
            // FOAF
            $this->xrd->links[] = new XML_XRD_Element_Link('describedby',
                                        common_local_url('foaf', array('nickname' => $nick)),
                                        'application/rdf+xml');

            $link = new XML_XRD_Element_Link('http://apinamespace.org/atom',
                                        common_local_url('ApiAtomService', array('id' => $nick)),
                                        'application/atomsvc+xml');
// XML_XRD must implement changing properties first           $link['http://apinamespace.org/atom/username'] = $nick;
            $this->xrd->links[] = clone $link;

            if (common_config('site', 'fancy')) {
                $apiRoot = common_path('api/', true);
            } else {
                $apiRoot = common_path('index.php/api/', true);
            }

            $link = new XML_XRD_Element_Link('http://apinamespace.org/twitter', $apiRoot);
// XML_XRD must implement changing properties first            $link['http://apinamespace.org/twitter/username'] = $nick;
            $this->xrd->links[] = clone $link;

            Event::handle('EndXrdActionLinks', array($this->xrd, $this->target));
        }
    }
}

<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2013, Free Software Foundation, Inc.
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
 * Implements WebFinger for GNU Social, as well as support for the
 * '.well-known/host-meta' resource.
 *
 * Depends on: LRDD plugin
 *
 * @package GNUsocial
 * @author  Mikael Nordfeldth <mmn@hethane.se>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class WebFingerPlugin extends Plugin
{
    const OAUTH_ACCESS_TOKEN_REL    = 'http://apinamespace.org/oauth/access_token';
    const OAUTH_REQUEST_TOKEN_REL   = 'http://apinamespace.org/oauth/request_token';
    const OAUTH_AUTHORIZE_REL       = 'http://apinamespace.org/oauth/authorize';

    public function onRouterInitialized($m)
    {
        $m->connect('.well-known/host-meta', array('action' => 'hostmeta'));
        $m->connect('.well-known/host-meta.:format',
                        array('action' => 'hostmeta',
                              'format' => '(xml|json)'));
        // the resource GET parameter can be anywhere, so don't mention it here
        $m->connect('.well-known/webfinger', array('action' => 'webfinger'));
        $m->connect('.well-known/webfinger.:format',
                        array('action' => 'webfinger',
                              'format' => '(xml|json)'));
        $m->connect('main/ownerxrd', array('action' => 'ownerxrd'));
        return true;
    }

    public function onLoginAction($action, &$login)
    {
        switch ($action) {
        case 'hostmeta':
        case 'webfinger':
            $login = true;
            return false;
        }
        
        return true;
    }

    public function onStartGetProfileAcctUri(Profile $profile, &$acct)
    {
        $wfr = new WebFingerResource_Profile($profile);
        try {
            $acct = $wfr->reconstructAcct();
        } catch (Exception $e) {
            return true;
        }

        return false;
    }

    public function onEndGetWebFingerResource($resource, WebFingerResource &$target=null, array $args=array())
    {
        $profile = null;
        if (Discovery::isAcct($resource)) {
            $parts = explode('@', substr(urldecode($resource), 5)); // 5 is strlen of 'acct:'
            if (count($parts) == 2) {
                list($nick, $domain) = $parts;
                if ($domain !== common_config('site', 'server')) {
                    throw new Exception(_('Remote profiles not supported via WebFinger yet.'));
                }

                $nick = common_canonical_nickname($nick);
                $user = User::getKV('nickname', $nick);
                if (!($user instanceof User)) {
                    throw new NoSuchUserException(array('nickname'=>$nick));
                }
                $profile = $user->getProfile();
            }
        } elseif (!common_valid_http_url($resource)) {
            // If it's not a URL, we can't do our http<->https legacy fix thingie anyway,
            // so just try the User URI lookup!
            try {
                $user = User::getByUri($resource);
                $profile = $user->getProfile();
            } catch (NoResultException $e) {
                // not a User, maybe a Notice? we'll try that further down...
            }
        } else {
            // this means $resource is a common_valid_http_url (or https)
            // First build up a set of alternative resource URLs that we can use.
            $alt_urls = [$resource => true];
            if (strtolower(parse_url($resource, PHP_URL_SCHEME)) === 'https'
                    && common_config('fix', 'legacy_http')) {
                $alt_urls[preg_replace('/^https:/i', 'http:', $resource, 1)] = true;
            }
            if (common_config('fix', 'fancyurls')) {
                foreach (array_keys($alt_urls) as $url) {
                    try {   // if it's a /index.php/ url
                        // common_fake_local_fancy_url can throw an exception
                        $alt_url = common_fake_local_fancy_url($url);
                    } catch (Exception $e) {    // let's try to create a fake local /index.php/ url
                        // this too if it can't do anything about the URL
                        $alt_url = common_fake_local_nonfancy_url($url);
                    }

                    $alt_urls[$alt_url] = true;
                }
            }
            common_debug(__METHOD__.': Generated these alternative URLs for various federation fixes: '._ve(array_keys($alt_urls)));

            try {
                common_debug(__METHOD__.': Finding User URI for WebFinger lookup on resource=='._ve($resource));
                $user = new User();
                $user->whereAddIn('uri', array_keys($alt_urls), $user->columnType('uri'));
                $user->limit(1);
                if ($user->find(true)) {
                    $profile = $user->getProfile();
                }
                unset($user);
            } catch (Exception $e) {
                // Most likely a UserNoProfileException, if it ever happens
                // and then we need to do some debugging and perhaps fixes.
                common_log(LOG_ERR, get_class($e).': '._ve($e->getMessage()));
                throw $e;
            }

            // User URI did not match, so let's try our alt_urls as Profile URL values
            if (!$profile instanceof Profile) {
                common_debug(__METHOD__.': Finding Profile URLs for WebFinger lookup on resource=='._ve($resource));
                // if our rewrite hack didn't work, try to get something by profile URL
                $profile = new Profile();
                $profile->whereAddIn('profileurl', array_keys($alt_urls), $profile->columnType('profileurl'));
                $profile->limit(1);
                if (!$profile->find(true) || !$profile->isLocal()) {
                    // * Either we didn't find the profile, then we want to make
                    //   the $profile variable null for clarity.
                    // * Or we did find it but for a possibly malicious remote
                    //   user who might've set their profile URL to a Notice URL
                    //   which would've caused a sort of DoS unless we continue
                    //   our search here by discarding the remote profile.
                    $profile = null;
                }
            }
        }

        if ($profile instanceof Profile) {
            common_debug(__METHOD__.': Found Profile with ID=='._ve($profile->getID()).' for resource=='._ve($resource));
            $target = new WebFingerResource_Profile($profile);
            return false;   // We got our target, stop handler execution
        }

        $notice = Notice::getKV('uri', $resource);
        if ($notice instanceof Notice) {
            $target = new WebFingerResource_Notice($notice);
            return false;
        }

        return true;
    }

    public function onStartHostMetaLinks(array &$links)
    {
        foreach (Discovery::supportedMimeTypes() as $type) {
            $links[] = new XML_XRD_Element_Link(Discovery::LRDD_REL,
                            common_local_url('webfinger') . '?resource={uri}',
                            $type,
                            true);    // isTemplate
        }

        // OAuth connections
        $links[] = new XML_XRD_Element_link(self::OAUTH_ACCESS_TOKEN_REL,  common_local_url('ApiOAuthAccessToken'));
        $links[] = new XML_XRD_Element_link(self::OAUTH_REQUEST_TOKEN_REL, common_local_url('ApiOAuthRequestToken'));
        $links[] = new XML_XRD_Element_link(self::OAUTH_AUTHORIZE_REL,     common_local_url('ApiOAuthAuthorize'));
    }

    /**
     * Add a link header for LRDD Discovery
     */
    public function onStartShowHTML($action)
    {
        if ($action instanceof ShowstreamAction) {
            $resource = $action->getTarget()->getUri();
            $url = common_local_url('webfinger') . '?resource='.urlencode($resource);

            foreach (array(Discovery::JRD_MIMETYPE, Discovery::XRD_MIMETYPE) as $type) {
                header('Link: <'.$url.'>; rel="'. Discovery::LRDD_REL.'"; type="'.$type.'"', false);
            }
        }
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'WebFinger',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://www.gnu.org/software/social/',
                            // TRANS: Plugin description.
                            'rawdescription' => _m('Adds WebFinger lookup to GNU Social'));

        return true;
    }
}

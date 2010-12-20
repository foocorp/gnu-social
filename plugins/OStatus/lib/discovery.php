<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
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
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

/**
 * This class implements LRDD-based service discovery based on the "Hammer Draft"
 * (including webfinger)
 *
 * @see http://groups.google.com/group/webfinger/browse_thread/thread/9f3d93a479e91bbf
 */
class Discovery
{

    const LRDD_REL = 'lrdd';
    const PROFILEPAGE = 'http://webfinger.net/rel/profile-page';
    const UPDATESFROM = 'http://schemas.google.com/g/2010#updates-from';
    const HCARD = 'http://microformats.org/profile/hcard';

    public $methods = array();

    public function __construct()
    {
        $this->registerMethod('Discovery_LRDD_Host_Meta');
        $this->registerMethod('Discovery_LRDD_Link_Header');
        $this->registerMethod('Discovery_LRDD_Link_HTML');
    }

    public function registerMethod($class)
    {
        $this->methods[] = $class;
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
        $uri = Discovery::normalize($user_id);

        return (substr($uri, 0, 5) == 'acct:');
    }

    /**
     * This implements the actual lookup procedure
     */
    public function lookup($id)
    {
        // Normalize the incoming $id to make sure we have a uri
        $uri = $this->normalize($id);

        foreach ($this->methods as $class) {
            $links = call_user_func(array($class, 'discover'), $uri);
            if ($link = Discovery::getService($links, Discovery::LRDD_REL)) {
                // Load the LRDD XRD
                if (!empty($link['template'])) {
                    $xrd_uri = Discovery::applyTemplate($link['template'], $uri);
                } else {
                    $xrd_uri = $link['href'];
                }

                $xrd = $this->fetchXrd($xrd_uri);
                if ($xrd) {
                    return $xrd;
                }
            }
        }

        // TRANS: Exception.
        throw new Exception(sprintf(_m('Unable to find services for %s.'),$id));
    }

    public static function getService($links, $service) {
        if (!is_array($links)) {
            return false;
        }

        foreach ($links as $link) {
            if ($link['rel'] == $service) {
                return $link;
            }
        }
    }

    public static function applyTemplate($template, $id)
    {
        $template = str_replace('{uri}', urlencode($id), $template);

        return $template;
    }

    public static function fetchXrd($url)
    {
        try {
            $client = new HTTPClient();
            $response = $client->get($url);
        } catch (HTTP_Request2_Exception $e) {
            return false;
        }

        if ($response->getStatus() != 200) {
            return false;
        }

        return XRD::parse($response->getBody());
    }
}

interface Discovery_LRDD
{
    public function discover($uri);
}

class Discovery_LRDD_Host_Meta implements Discovery_LRDD
{
    public function discover($uri)
    {
        if (Discovery::isWebfinger($uri)) {
            // We have a webfinger acct: - start with host-meta
            list($name, $domain) = explode('@', $uri);
        } else {
            $domain = parse_url($uri, PHP_URL_HOST);
        }

        $url = 'http://'. $domain .'/.well-known/host-meta';

        $xrd = Discovery::fetchXrd($url);

        if ($xrd) {
            if ($xrd->host != $domain) {
                return false;
            }

            return $xrd->links;
        }
    }
}

class Discovery_LRDD_Link_Header implements Discovery_LRDD
{
    public function discover($uri)
    {
        try {
            $client = new HTTPClient();
            $response = $client->get($uri);
        } catch (HTTP_Request2_Exception $e) {
            return false;
        }

        if ($response->getStatus() != 200) {
            return false;
        }

        $link_header = $response->getHeader('Link');
        if (!$link_header) {
            //            return false;
        }

        return array(Discovery_LRDD_Link_Header::parseHeader($link_header));
    }

    protected static function parseHeader($header)
    {
        $lh = new LinkHeader($header);

        return array('href' => $lh->href,
                     'rel'  => $lh->rel,
                     'type' => $lh->type);
    }
}

class Discovery_LRDD_Link_HTML implements Discovery_LRDD
{
    public function discover($uri)
    {
        try {
            $client = new HTTPClient();
            $response = $client->get($uri);
        } catch (HTTP_Request2_Exception $e) {
            return false;
        }

        if ($response->getStatus() != 200) {
            return false;
        }

        return Discovery_LRDD_Link_HTML::parse($response->getBody());
    }

    public function parse($html)
    {
        $links = array();

        preg_match('/<head(\s[^>]*)?>(.*?)<\/head>/is', $html, $head_matches);
        $head_html = $head_matches[2];

        preg_match_all('/<link\s[^>]*>/i', $head_html, $link_matches);

        foreach ($link_matches[0] as $link_html) {
            $link_url = null;
            $link_rel = null;
            $link_type = null;

            preg_match('/\srel=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $rel_matches);
            if ( isset($rel_matches[3]) ) {
                $link_rel = $rel_matches[3];
            } else if ( isset($rel_matches[1]) ) {
                $link_rel = $rel_matches[1];
            }

            preg_match('/\shref=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $href_matches);
            if ( isset($href_matches[3]) ) {
                $link_uri = $href_matches[3];
            } else if ( isset($href_matches[1]) ) {
                $link_uri = $href_matches[1];
            }

            preg_match('/\stype=(("|\')([^\\2]*?)\\2|[^"\'\s]+)/i', $link_html, $type_matches);
            if ( isset($type_matches[3]) ) {
                $link_type = $type_matches[3];
            } else if ( isset($type_matches[1]) ) {
                $link_type = $type_matches[1];
            }

            $links[] = array(
                'href' => $link_url,
                'rel' => $link_rel,
                'type' => $link_type,
            );
        }

        return $links;
    }
}

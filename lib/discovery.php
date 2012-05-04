<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Use Hammer discovery stack to find out interesting things about an URI
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
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * This class implements LRDD-based service discovery based on the "Hammer Draft"
 * (including webfinger)
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 *
 * @see       http://groups.google.com/group/webfinger/browse_thread/thread/9f3d93a479e91bbf
 */
class Discovery
{
    const LRDD_REL    = 'lrdd';
    const PROFILEPAGE = 'http://webfinger.net/rel/profile-page';
    const UPDATESFROM = 'http://schemas.google.com/g/2010#updates-from';
    const HCARD       = 'http://microformats.org/profile/hcard';

    public $methods = array();

    /**
     * Constructor for a discovery object
     *
     * Registers different discovery methods.
     *
     * @return Discovery this
     */

    public function __construct()
    {
        $this->registerMethod('Discovery_LRDD_Host_Meta');
        $this->registerMethod('Discovery_LRDD_Link_Header');
        $this->registerMethod('Discovery_LRDD_Link_HTML');
    }

    /**
     * Register a discovery class
     *
     * @param string $class Class name
     *
     * @return void
     */
    public function registerMethod($class)
    {
        $this->methods[] = $class;
    }

    /**
     * Given a "user id" make sure it's normalized to either a webfinger
     * acct: uri or a profile HTTP URL.
     *
     * @param string $user_id User ID to normalize
     *
     * @return string normalized acct: or http(s)?: URI
     */
    public static function normalize($user_id)
    {
        if (substr($user_id, 0, 5) == 'http:' ||
            substr($user_id, 0, 6) == 'https:' ||
            substr($user_id, 0, 5) == 'acct:') {
            return $user_id;
        }

        if (strpos($user_id, '@') !== false) {
            return 'acct:' . $user_id;
        }

        return 'http://' . $user_id;
    }

    /**
     * Determine if a string is a Webfinger ID
     *
     * Webfinger IDs look like foo@example.com or acct:foo@example.com
     *
     * @param string $user_id ID to check
     *
     * @return boolean true if $user_id is a Webfinger, else false
     */
    public static function isWebfinger($user_id)
    {
        $uri = Discovery::normalize($user_id);

        return (substr($uri, 0, 5) == 'acct:');
    }

    /**
     * Given a user ID, return the first available XRD
     *
     * @param string $id User ID URI
     *
     * @return XRD XRD object for the user
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

        // TRANS: Exception. %s is an ID.
        throw new Exception(sprintf(_('Unable to find services for %s.'), $id));
    }

    /**
     * Given an array of links, returns the matching service
     *
     * @param array  $links   Links to check
     * @param string $service Service to find
     *
     * @return array $link assoc array representing the link
     */
    public static function getService($links, $service)
    {
        if (!is_array($links)) {
            return false;
        }

        foreach ($links as $link) {
            if ($link['rel'] == $service) {
                return $link;
            }
        }
    }

    /**
     * Apply a template using an ID
     *
     * Replaces {uri} in template string with the ID given.
     *
     * @param string $template Template to match
     * @param string $id       User ID to replace with
     *
     * @return string replaced values
     */
    public static function applyTemplate($template, $id)
    {
        $template = str_replace('{uri}', urlencode($id), $template);

        return $template;
    }

    /**
     * Fetch an XRD file and parse
     *
     * @param string $url URL of the XRD
     *
     * @return XRD object representing the XRD file
     */
    public static function fetchXrd($url)
    {
        try {
            $client   = new HTTPClient();
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

/**
 * Abstract interface for discovery
 *
 * Objects that implement this interface can retrieve an array of
 * XRD links for the URI.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
interface Discovery_LRDD
{
    /**
     * Discover interesting info about the URI
     *
     * @param string $uri URI to inquire about
     *
     * @return array Links in the XRD file
     */
    public function discover($uri);
}

/**
 * Implementation of discovery using host-meta file
 *
 * Discovers XRD file for a user by going to the organization's
 * host-meta file and trying to find a template for LRDD.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Discovery_LRDD_Host_Meta implements Discovery_LRDD
{
    /**
     * Discovery core method
     *
     * For Webfinger and HTTP URIs, fetch the host-meta file
     * and look for LRDD templates
     *
     * @param string $uri URI to inquire about
     *
     * @return array Links in the XRD file
     */
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
            return $xrd->links;
        }
    }
}

/**
 * Implementation of discovery using HTTP Link header
 *
 * Discovers XRD file for a user by fetching the URL and reading any
 * Link: headers in the HTTP response.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Discovery_LRDD_Link_Header implements Discovery_LRDD
{
    /**
     * Discovery core method
     *
     * For HTTP IDs fetch the URL and look for Link headers.
     *
     * @param string $uri URI to inquire about
     *
     * @return array Links in the XRD file
     *
     * @todo fail out of Webfinger URIs faster
     */
    public function discover($uri)
    {
        try {
            $client   = new HTTPClient();
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

    /**
     * Given a string or array of headers, returns XRD-like assoc array
     *
     * @param string|array $header string or array of strings for headers
     *
     * @return array Link header in XRD-like format
     */
    protected static function parseHeader($header)
    {
        $lh = new LinkHeader($header);

        return array('href' => $lh->href,
                     'rel'  => $lh->rel,
                     'type' => $lh->type);
    }
}

/**
 * Implementation of discovery using HTML <link> element
 *
 * Discovers XRD file for a user by fetching the URL and reading any
 * <link> elements in the HTML response.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Discovery_LRDD_Link_HTML implements Discovery_LRDD
{
    /**
     * Discovery core method
     *
     * For HTTP IDs, fetch the URL and look for <link> elements
     * in the HTML response.
     *
     * @param string $uri URI to inquire about
     *
     * @return array Links in XRD-ish assoc array
     *
     * @todo fail out of Webfinger URIs faster
     */
    public function discover($uri)
    {
        try {
            $client   = new HTTPClient();
            $response = $client->get($uri);
        } catch (HTTP_Request2_Exception $e) {
            return false;
        }

        if ($response->getStatus() != 200) {
            return false;
        }

        return Discovery_LRDD_Link_HTML::parse($response->getBody());
    }

    /**
     * Parse HTML and return <link> elements
     *
     * Given an HTML string, scans the string for <link> elements
     *
     * @param string $html HTML to scan
     *
     * @return array array of associative arrays in XRD-ish format
     */
    public function parse($html)
    {
        $links = array();

        preg_match('/<head(\s[^>]*)?>(.*?)<\/head>/is', $html, $head_matches);
        $head_html = $head_matches[2];

        preg_match_all('/<link\s[^>]*>/i', $head_html, $link_matches);

        foreach ($link_matches[0] as $link_html) {
            $link_url  = null;
            $link_rel  = null;
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

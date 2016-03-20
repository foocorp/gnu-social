<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This class performs lookups based on methods implemented in separate
 * classes, where a resource uri is given. Examples are WebFinger (RFC7033)
 * and the LRDD (Link-based Resource Descriptor Discovery) in RFC6415.
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
 * @package   GNUsocial
 * @author    James Walker <james@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2010 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class Discovery
{
    const LRDD_REL    = 'lrdd';
    const HCARD       = 'http://microformats.org/profile/hcard';
    const MF2_HCARD   = 'http://microformats.org/profile/h-card';   // microformats2 h-card

    const JRD_MIMETYPE_OLD = 'application/json';    // RFC6415 uses this
    const JRD_MIMETYPE = 'application/jrd+json';
    const XRD_MIMETYPE = 'application/xrd+xml';

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
        if (Event::handle('StartDiscoveryMethodRegistration', array($this))) {
            Event::handle('EndDiscoveryMethodRegistration', array($this));
        }
    }

    public static function supportedMimeTypes()
    {
        return array('json'=>self::JRD_MIMETYPE,
                     'jsonold'=>self::JRD_MIMETYPE_OLD,
                     'xml'=>self::XRD_MIMETYPE);
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
     * Given a user ID, return the first available resource descriptor
     *
     * @param string $id User ID URI
     *
     * @return XML_XRD object for the resource descriptor of the id
     */
    public function lookup($id)
    {
        // Normalize the incoming $id to make sure we have a uri
        $uri = self::normalize($id);

        foreach ($this->methods as $class) {
            try {
                $xrd = new XML_XRD();

                common_debug("LRDD discovery method for '$uri': {$class}");
                $lrdd = new $class;
                $links = call_user_func(array($lrdd, 'discover'), $uri);
                $link = Discovery::getService($links, Discovery::LRDD_REL);

                // Load the LRDD XRD
                if (!empty($link->template)) {
                    $xrd_uri = Discovery::applyTemplate($link->template, $uri);
                } elseif (!empty($link->href)) {
                    $xrd_uri = $link->href;
                } else {
                    throw new Exception('No resource descriptor URI in link.');
                }

                $client  = new HTTPClient();
                $headers = array();
                if (!is_null($link->type)) {
                    $headers[] = "Accept: {$link->type}";
                }

                $response = $client->get($xrd_uri, $headers);
                if ($response->getStatus() != 200) {
                    throw new Exception('Unexpected HTTP status code.');
                }

                $xrd->loadString($response->getBody());
                return $xrd;
            } catch (Exception $e) {
                continue;
            }
        }

        // TRANS: Exception. %s is an ID.
        throw new Exception(sprintf(_('Unable to find services for %s.'), $id));
    }

    /**
     * Given an array of links, returns the matching service
     *
     * @param array  $links   Links to check (as instances of XML_XRD_Element_Link)
     * @param string $service Service to find
     *
     * @return array $link assoc array representing the link
     */
    public static function getService(array $links, $service)
    {
        foreach ($links as $link) {
            if ($link->rel === $service) {
                return $link;
            }
            common_debug('LINK: rel '.$link->rel.' !== '.$service);
        }

        throw new Exception('No service link found');
    }

    /**
     * Given a "user id" make sure it's normalized to an acct: uri
     *
     * @param string $user_id User ID to normalize
     *
     * @return string normalized acct: URI
     */
    public static function normalize($uri)
    {
        if (is_null($uri) || $uri==='') {
            throw new Exception(_('No resource given.'));
        }

        $parts = parse_url($uri);
        // If we don't have a scheme, but the path implies user@host,
        // though this is far from a perfect matching procedure...
        if (!isset($parts['scheme']) && isset($parts['path'])
                && preg_match('/[\w@\w]/u', $parts['path'])) {
            return 'acct:' . $uri;
        }

        return $uri;
    }

    public static function isAcct($uri)
    {
        return (mb_strtolower(mb_substr($uri, 0, 5)) == 'acct:');
    }

    /**
     * Apply a template using an ID
     *
     * Replaces {uri} in template string with the ID given.
     *
     * @param string $template Template to match
     * @param string $uri      URI to replace with
     *
     * @return string replaced values
     */
    public static function applyTemplate($template, $uri)
    {
        $template = str_replace('{uri}', urlencode($uri), $template);

        return $template;
    }
}

<?php
/**
 * This file is part of libomb
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
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
 * @package OMB
 * @author  Adrian Lang <mail@adrianlang.de>
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 * @version 0.1a-20090828
 * @link    http://adrianlang.de/libomb
 */

require_once 'Auth/Yadis/Yadis.php';
require_once 'unsupportedserviceexception.php';
require_once 'invalidyadisexception.php';

/**
 * OMB XRDS representation
 *
 * This class represents a Yadis XRDS file for OMB. It adds some useful methods to
 * Auth_Yadis_XRDS.
 */
class OMB_Yadis_XRDS extends Auth_Yadis_XRDS
{

    protected $fetcher;

    /**
     * Create an instance from URL
     *
     * Constructs an OMB_Yadis_XRDS object from a given URL. A full Yadis
     * discovery is performed on the URL and the XRDS is parsed.
     * Throws an OMB_InvalidYadisException when no Yadis is discovered or the
     * detected XRDS file is broken.
     *
     * @param string                 $url     The URL on which Yadis discovery
     *                                        should be performed on
     * @param Auth_Yadis_HTTPFetcher $fetcher A fetcher used to get HTTP
     *                                        resources
     *
     * @access public
     *
     * @return OMB_Yadis_XRDS The initialized object representing the given
     *                        resource
     */
    public static function fromYadisURL($url, $fetcher)
    {
        /* Perform a Yadis discovery. */
        $yadis = Auth_Yadis_Yadis::discover($url, $fetcher);
        if ($yadis->failed) {
            throw new OMB_InvalidYadisException($url);
        }

        /* Parse the XRDS file. */
        $xrds = OMB_Yadis_XRDS::parseXRDS($yadis->response_text);
        if ($xrds === null) {
            throw new OMB_InvalidYadisException($url);
        }
        $xrds->fetcher = $fetcher;
        return $xrds;
    }

    /**
     * Get a specific service
     *
     * Returns the Auth_Yadis_Service object corresponding to the given service
     * URI.
     * Throws an OMB_UnsupportedServiceException if the service is not
     * available.
     *
     * @param string $service URI specifier of the requested service
     *
     * @access public
     *
     * @return Auth_Yadis_Service The object representing the requested service
     */
    public function getService($service)
    {
        $match = $this->services(array(create_function('$s',
                             "return in_array('$service', \$s->getTypes());")));
        if ($match === array()) {
            throw new OMB_UnsupportedServiceException($service);
        }
        return $match[0];
    }

    /**
     * Get a specific XRD
     *
     * Returns the OMB_Yadis_XRDS object corresponding to the given URI.
     * Throws an OMB_UnsupportedServiceException if the XRD is not available.
     * Note that getXRD tries to resolve external XRD parts as well.
     *
     * @param string $uri URI specifier of the requested XRD
     *
     * @access public
     *
     * @return OMB_Yadis_XRDS The object representing the requested XRD
     */
    public function getXRD($uri)
    {
        $nexthash = strpos($uri, '#');
        if ($nexthash === false) {
            throw new OMB_InvalidYadisException("‘$uri’ does not specify a " .
                                                'valid XML node.');
        }

        if ($nexthash > 0) {
            $cururi  = substr($uri, 0, $nexthash);
            $nexturi = substr($uri, $nexthash);
            return OMB_Yadis_XRDS::fromYadisURL($cururi, $this->fetcher)
                                               ->getXRD($nexturi);
        }

        $id = substr($uri, 1);
        foreach ($this->allXrdNodes as $node) {
            $attrs = $this->parser->attributes($node);
            if (array_key_exists('xml:id', $attrs) && $attrs['xml:id'] == $id) {
                /* Trick the constructor into thinking this is the only node. */
                $bogus_nodes = array($node);
                return new OMB_Yadis_XRDS($this->parser, $bogus_nodes);
            }
        }
        throw new OMB_UnsupportedServiceException($uri);
    }

    /**
     * Parse an XML string containing a XRDS document
     *
     * Parses an XML string (XRDS document) and returns either an
     * Auth_Yadis_XRDS object or null, depending on whether the XRDS XML is
     * valid.
     * This method is just copy and paste from the parent class to select the
     * correct constructor.
     *
     * @param string $xml_string   An XRDS XML string
     * @param array  $extra_ns_map Additional namespace declarations
     *
     * @access public
     *
     * @return mixed An instance of OMB_Yadis_XRDS or null,
     *               depending on the validity of $xml_string
     */
    public static function parseXRDS($xml_string, $extra_ns_map = null)
    {
        $_null = null;

        if (!$xml_string) {
            return $_null;
        }

        $parser = Auth_Yadis_getXMLParser();

        $ns_map = Auth_Yadis_getNSMap();

        if ($extra_ns_map && is_array($extra_ns_map)) {
            $ns_map = array_merge($ns_map, $extra_ns_map);
        }

        if (!($parser && $parser->init($xml_string, $ns_map))) {
            return $_null;
        }

        // Try to get root element.
        $root = $parser->evalXPath('/xrds:XRDS[1]');
        if (!$root) {
            return $_null;
        }

        if (is_array($root)) {
            $root = $root[0];
        }

        $attrs = $parser->attributes($root);

        if (array_key_exists('xmlns:xrd', $attrs) &&
                    $attrs['xmlns:xrd'] != Auth_Yadis_XMLNS_XRDS) {
            return $_null;
        } else if (array_key_exists('xmlns', $attrs) &&
                                     preg_match('/xri/', $attrs['xmlns']) &&
                                     $attrs['xmlns'] != Auth_Yadis_XMLNS_XRD_2_0) {
            return $_null;
        }

        // Get the last XRD node.
        $xrd_nodes = $parser->evalXPath('/xrds:XRDS[1]/xrd:XRD');

        if (!$xrd_nodes) {
            return $_null;
        }

        $xrds = new OMB_Yadis_XRDS($parser, $xrd_nodes);
        return $xrds;
    }
}

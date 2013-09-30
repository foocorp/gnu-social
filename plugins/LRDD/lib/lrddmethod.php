<?php
/**
 * Abstract class for LRDD discovery methods
 *
 * Objects that extend this class can retrieve an array of
 * resource descriptor links for the URI. The array consists
 * of XML_XRD_Element_Link elements.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class LRDDMethod
{
    protected $xrd = null;

    public function __construct() {
        $this->xrd = new XML_XRD();
    }

    /**
     * Discover interesting info about the URI
     *
     * @param string $uri URI to inquire about
     *
     * @return array of XML_XRD_Element_Link elements to discovered resource descriptors
     */
    abstract public function discover($uri);

    protected function fetchUrl($url, $method=HTTPClient::METHOD_GET)
    {
        $client  = new HTTPClient();

        // GAAHHH, this method sucks! How about we make a better HTTPClient interface?
        switch ($method) {
        case HTTPClient::METHOD_GET:
            $response = $client->get($url);
            break;
        case HTTPClient::METHOD_HEAD:
            $response = $client->head($url);
            break;
        default:
            throw new Exception('Bad HTTP method.');
        }

        if ($response->getStatus() != 200) {
            throw new Exception('Unexpected HTTP status code.');
        }

        return $response;
    }
}

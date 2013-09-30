<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Parse HTTP response for interesting Link: headers
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
 * Class to represent Link: headers in an HTTP response
 *
 * Since these are a fairly important part of Hammer-stack discovery, they're
 * reified and implemented here.
 *
 * @category  Discovery
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 *
 * @see       Discovery
 */
class LinkHeader
{
    var $href;
    var $rel;
    var $type;

    /**
     * Initialize from a string
     *
     * @param string $str Link: header value
     *
     * @return LinkHeader self
     */
    function __construct($str)
    {
        preg_match('/^<[^>]+>/', $str, $uri_reference);
        //if (empty($uri_reference)) return;

        $this->href = trim($uri_reference[0], '<>');
        $this->rel  = array();
        $this->type = null;

        // remove uri-reference from header
        $str = substr($str, strlen($uri_reference[0]));

        // parse link-params
        $params = explode(';', $str);

        foreach ($params as $param) {
            if (empty($param)) {
                continue;
            }
            list($param_name, $param_value) = explode('=', $param, 2);

            $param_name  = trim($param_name);
            $param_value = preg_replace('(^"|"$)', '', trim($param_value));

            // for now we only care about 'rel' and 'type' link params
            // TODO do something with the other links-params
            switch ($param_name) {
            case 'rel':
                $this->rel = trim($param_value);
                break;

            case 'type':
                $this->type = trim($param_value);
            }
        }
    }

    /**
     * Given an HTTP response, return the requested Link: header
     *
     * @param HTTP_Request2_Response $response response to check
     * @param string                 $rel      relationship to look for
     * @param string                 $type     media type to look for
     *
     * @return LinkHeader discovered header, or null on failure
     */
    static function getLink($response, $rel=null, $type=null)
    {
        $headers = $response->getHeader('Link');
        if ($headers) {
            // Can get an array or string, so try to simplify the path
            if (!is_array($headers)) {
                $headers = array($headers);
            }

            foreach ($headers as $header) {
                $lh = new LinkHeader($header);

                if ((is_null($rel) || $lh->rel == $rel) &&
                    (is_null($type) || $lh->type == $type)) {
                    return $lh->href;
                }
            }
        }
        return null;
    }
}

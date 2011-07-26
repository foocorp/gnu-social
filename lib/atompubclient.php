<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Client class for AtomPub
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
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Client class for AtomPub
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class AtomPubClient
{
    public $url;
    private $user, $pass;

    /**
     *
     * @param string $url collection feed URL
     * @param string $user auth username
     * @param string $pass auth password
     */
    function __construct($url, $user, $pass)
    {
        $this->url = $url;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * Set up an HTTPClient with auth for our resource.
     *
     * @param string $method
     * @return HTTPClient
     */
    private function httpClient($method='GET')
    {
        $client = new HTTPClient($this->url);
        $client->setMethod($method);
        $client->setAuth($this->user, $this->pass);
        return $client;
    }

    function get()
    {
        $client = $this->httpClient('GET');
        $response = $client->send();
        if ($response->isOk()) {
            return $response->getBody();
        } else {
            throw new Exception("Bogus return code: " . $response->getStatus() . ': ' . $response->getBody());
        }
    }

    /**
     * Create a new resource by POSTing it to the collection.
     * If successful, will return the URL representing the
     * canonical location of the new resource. Neat!
     *
     * @param string $data
     * @param string $type defaults to Atom entry
     * @return string URL to the created resource
     *
     * @throws exceptions on failure
     */
    function post($data, $type='application/atom+xml;type=entry')
    {
        $client = $this->httpClient('POST');
        $client->setHeader('Content-Type', $type);
        // optional Slug header not used in this case
        $client->setBody($data);
        $response = $client->send();

        if ($response->getStatus() != '201') {
            throw new Exception("Expected HTTP 201 on POST, got " . $response->getStatus() . ': ' . $response->getBody());
        }
        $loc = $response->getHeader('Location');
        $contentLoc = $response->getHeader('Content-Location');

        if (empty($loc)) {
            throw new Exception("AtomPub POST response missing Location header.");
        }
        if (!empty($contentLoc)) {
            if ($loc != $contentLoc) {
                throw new Exception("AtomPub POST response Location and Content-Location headers do not match.");
            }

            // If Content-Location and Location match, that means the response
            // body is safe to interpret as the resource itself.
            if ($type == 'application/atom+xml;type=entry') {
                self::validateAtomEntry($response->getBody());
            }
        }

        return $loc;
    }

    /**
     * Note that StatusNet currently doesn't allow PUT editing on notices.
     *
     * @param string $data
     * @param string $type defaults to Atom entry
     * @return true on success
     *
     * @throws exceptions on failure
     */
    function put($data, $type='application/atom+xml;type=entry')
    {
        $client = $this->httpClient('PUT');
        $client->setHeader('Content-Type', $type);
        $client->setBody($data);
        $response = $client->send();

        if ($response->getStatus() != '200' && $response->getStatus() != '204') {
            throw new Exception("Expected HTTP 200 or 204 on PUT, got " . $response->getStatus() . ': ' . $response->getBody());
        }

        return true;
    }

    /**
     * Delete the resource.
     *
     * @return true on success
     *
     * @throws exceptions on failure
     */
    function delete()
    {
        $client = $this->httpClient('DELETE');
        $client->setBody($data);
        $response = $client->send();

        if ($response->getStatus() != '200' && $response->getStatus() != '204') {
            throw new Exception("Expected HTTP 200 or 204 on DELETE, got " . $response->getStatus() . ': ' . $response->getBody());
        }

        return true;
    }

    /**
     * Ensure that the given string is a parseable Atom entry.
     *
     * @param string $str
     * @return boolean
     * @throws Exception on invalid input
     */
    static function validateAtomEntry($str)
    {
        if (empty($str)) {
            throw new Exception('Bad Atom entry: empty');
        }
        $dom = new DOMDocument;
        if (!$dom->loadXML($str)) {
            throw new Exception('Bad Atom entry: XML is not well formed.');
        }

        $activity = new Activity($dom->documentRoot);
        return true;
    }

    static function entryEditURL($str) {
        $dom = new DOMDocument;
        $dom->loadXML($str);
        $path = new DOMXPath($dom);
        $path->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        $links = $path->query('/atom:entry/atom:link[@rel="edit"]', $dom->documentRoot);
        if ($links && $links->length) {
            if ($links->length > 1) {
                throw new Exception('Bad Atom entry; has multiple rel=edit links.');
            }
            $link = $links->item(0);
            $url = $link->getAttribute('href');
            return $url;
        } else {
            throw new Exception('Atom entry lists no rel=edit link.');
        }
    }

    static function entryId($str) {
        $dom = new DOMDocument;
        $dom->loadXML($str);
        $path = new DOMXPath($dom);
        $path->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        $links = $path->query('/atom:entry/atom:id', $dom->documentRoot);
        if ($links && $links->length) {
            if ($links->length > 1) {
                throw new Exception('Bad Atom entry; has multiple id entries.');
            }
            $link = $links->item(0);
            $url = $link->textContent;
            return $url;
        } else {
            throw new Exception('Atom entry lists no id.');
        }
    }

    static function getEntryInFeed($str, $id)
    {
        $dom = new DOMDocument;
        $dom->loadXML($str);
        $path = new DOMXPath($dom);
        $path->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        $query = '/atom:feed/atom:entry[atom:id="'.$id.'"]';
        $items = $path->query($query, $dom->documentRoot);
        if ($items && $items->length) {
            return $items->item(0);
        } else {
            return null;
        }
    }
}

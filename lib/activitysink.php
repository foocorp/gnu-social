<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A remote, atompub-receiving service
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
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A remote service that supports AtomPub
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ActivitySink
{
    protected $svcDocUrl   = null;
    protected $username    = null;
    protected $password    = null;
    protected $collections = array();

    function __construct($svcDocUrl, $username, $password)
    {
        $this->svcDocUrl = $svcDocUrl;
        $this->username  = $username;
        $this->password  = $password;

        $this->_parseSvcDoc();
    }

    private function _parseSvcDoc()
    {
        $client   = new HTTPClient();
        $response = $client->get($this->svcDocUrl);

        if ($response->getStatus() != 200) {
            throw new Exception("Can't get {$this->svcDocUrl}; response status " . $response->getStatus());
        }

        $xml = $response->getBody();

        $dom = new DOMDocument();

        // We don't want to bother with white spaces
        $dom->preserveWhiteSpace = false;

        // Don't spew XML warnings to output
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        $ok = $dom->loadXML($xml);
        error_reporting($old);

        $path = new DOMXPath($dom);

        $path->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $path->registerNamespace('app', 'http://www.w3.org/2007/app');
        $path->registerNamespace('activity', 'http://activitystrea.ms/spec/1.0/');

        $collections = $path->query('//app:collection');

        for ($i = 0; $i < $collections->length; $i++) {
            $collection = $collections->item($i);
            $url = $collection->getAttribute('href');
            $takesEntries = false;
            $accepts = $path->query('app:accept', $collection);
            for ($j = 0; $j < $accepts->length; $j++) {
                $accept = $accepts->item($j);
                $acceptValue = $accept->nodeValue;
                if (preg_match('#application/atom\+xml(;\s*type=entry)?#', $acceptValue)) {
                    $takesEntries = true;
                    break;
                }
            }

            if (!$takesEntries) {
                continue;
            }
            $verbs = $path->query('activity:verb', $collection);
            if ($verbs->length == 0) {
                $this->_addCollection(ActivityVerb::POST, $url);
            } else {
                for ($k = 0; $k < $verbs->length; $k++) {
                    $verb = $verbs->item($k);
                    $this->_addCollection($verb->nodeValue, $url);
                }
            }
        }
    }

    private function _addCollection($verb, $url)
    {
        if (array_key_exists($verb, $this->collections)) {
            $this->collections[$verb][] = $url;
        } else {
            $this->collections[$verb] = array($url);
        }
        return;
    }

    function postActivity($activity)
    {
        if (!array_key_exists($activity->verb, $this->collections)) {
            throw new Exception("No collection for verb {$activity->verb}");
        } else {
            if (count($this->collections[$activity->verb]) > 1) {
                common_log(LOG_NOTICE, "More than one collection for verb {$activity->verb}");
            }
            $this->postToCollection($this->collections[$activity->verb][0], $activity);
        }
    }

    function postToCollection($url, $activity)
    {
        $client = new HTTPClient($url);

        $client->setMethod('POST');
        $client->setAuth($this->username, $this->password);
        $client->setHeader('Content-Type', 'application/atom+xml;type=entry');
        $client->setBody($activity->asString(true, true, true));

        $response = $client->send();

        $status = $response->getStatus();
        $reason = $response->getReasonPhrase();

        if ($status >= 200 && $status < 300) {
            return true;
        } else if ($status >= 400 && $status < 500) {
            // TRANS: Client exception thrown when post to collection fails with a 400 status.
            // TRANS: %1$s is a URL, %2$s is the status, %s$s is the fail reason.
            throw new ClientException(sprintf(_m('URLSTATUSREASON','%1$s %2$s %3$s'), $url, $status, $reason));
        } else if ($status >= 500 && $status < 600) {
            // TRANS: Server exception thrown when post to collection fails with a 500 status.
            // TRANS: %1$s is a URL, %2$s is the status, %s$s is the fail reason.
            throw new ServerException(sprintf(_m('URLSTATUSREASON','%1$s %2$s %3$s'), $url, $status, $reason));
        } else {
            // That's unexpected.
            // TRANS: Exception thrown when post to collection fails with a status that is not handled.
            // TRANS: %1$s is a URL, %2$s is the status, %s$s is the fail reason.
            throw new Exception(sprintf(_m('URLSTATUSREASON','%1$s %2$s %3$s'), $url, $status, $reason));
        }
    }
}

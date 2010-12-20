#!/usr/bin/env php
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'n:p:';
$longoptions = array('nickname=', 'password=', 'dry-run');

$helptext = <<<END_OF_HELP
USAGE: atompub_test.php [options]

Runs some tests on the AtomPub interface for the site. You must provide
a user account to authenticate as; it will be used to make some test
posts on the site.

Options:
  -n<user>  --nickname=<user>  Nickname of account to post as
  -p<pass>  --password=<pass>  Password for account
  --dry-run                    Skip tests that modify the site (post, delete)

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

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


$user = get_option_value('n', 'nickname');
$pass = get_option_value('p', 'password');

if (!$user) {
    die("Must set a user: --nickname=<username>\n");
}
if (!$pass) {
    die("Must set a password: --password=<username>\n");
}

// discover the feed...
// @fixme will this actually work?
$url = common_local_url('ApiTimelineUser', array('format' => 'atom', 'id' => $user));

echo "Collection URL is: $url\n";

$collection = new AtomPubClient($url, $user, $pass);

// confirm the feed has edit links ..... ?

echo "Posting an empty message (should fail)... ";
try {
    $noticeUrl = $collection->post('');
    die("FAILED, succeeded!\n");
} catch (Exception $e) {
    echo "ok\n";
}

echo "Posting an invalid XML message (should fail)... ";
try {
    $noticeUrl = $collection->post('<feed<entry>barf</yomomma>');
    die("FAILED, succeeded!\n");
} catch (Exception $e) {
    echo "ok\n";
}

echo "Posting a valid XML but non-Atom message (should fail)... ";
try {
    $noticeUrl = $collection->post('<feed xmlns="http://notatom.com"><id>arf</id><entry><id>barf</id></entry></feed>');
    die("FAILED, succeeded!\n");
} catch (Exception $e) {
    echo "ok\n";
}

// post!
$rand = mt_rand(0, 99999);
$atom = <<<END_ATOM
<entry xmlns="http://www.w3.org/2005/Atom">
    <title>This is an AtomPub test post title ($rand)</title>
    <content>This is an AtomPub test post content ($rand)</content>
</entry>
END_ATOM;

echo "Posting a new message... ";
$noticeUrl = $collection->post($atom);
echo "ok, got $noticeUrl\n";

echo "Fetching the new notice... ";
$notice = new AtomPubClient($noticeUrl, $user, $pass);
$body = $notice->get();
AtomPubClient::validateAtomEntry($body);
echo "ok\n";

echo "Getting the notice ID URI... ";
$noticeUri = AtomPubClient::entryId($body);
echo "ok: $noticeUri\n";

echo "Confirming new entry points to itself right... ";
$editUrl = AtomPubClient::entryEditURL($body);
if ($editUrl != $noticeUrl) {
    die("Entry lists edit URL as $editUrl, no match!\n");
}
echo "OK\n";

echo "Refetching the collection... ";
$feed = $collection->get();
echo "ok\n";

echo "Confirming new entry is in the feed... ";
$entry = AtomPubClient::getEntryInFeed($feed, $noticeUri);
if (!$entry) {
    die("missing!\n");
}
//  edit URL should match
echo "ok\n";

echo "Editing notice (should fail)... ";
try {
    $notice->put($target, $atom2);
    die("ERROR: editing a notice should have failed.\n");
} catch (Exception $e) {
    echo "ok (failed as expected)\n";
}

echo "Deleting notice... ";
$notice->delete();
echo "ok\n";

echo "Refetching deleted notice to confirm it's gone... ";
try {
    $body = $notice->get();
    var_dump($body);
    die("ERROR: notice should be gone now.\n");
} catch (Exception $e) {
    echo "ok\n";
}

echo "Refetching the collection.. ";
$feed = $collection->get();
echo "ok\n";

echo "Confirming deleted notice is no longer in the feed... ";
$entry = AtomPubClient::getEntryInFeed($feed, $noticeUri);
if ($entry) {
    die("still there!\n");
}
echo "ok\n";

// make subscriptions
// make some posts
// make sure the posts go through or not depending on the subs
// remove subscriptions
// test that they don't go through now

// group memberships too




// make sure we can't post to someone else's feed!
// make sure we can't delete someone else's messages
// make sure we can't create/delete someone else's subscriptions
// make sure we can't create/delete someone else's group memberships


#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_IMPORTTWITTERATOM_HELP
importtwitteratom.php [options]
import an Atom feed from Twitter as notices by a user

  -i --id       ID of user to update
  -n --nickname nickname of the user to update
  -f --file     file to import (Atom-only for now)

END_OF_IMPORTTWITTERATOM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

function getAtomFeedDocument()
{
    $filename = get_option_value('f', 'file');

    if (empty($filename)) {
        show_help();
        exit(1);
    }

    if (!file_exists($filename)) {
        throw new Exception("No such file '$filename'.");
    }

    if (!is_file($filename)) {
        throw new Exception("Not a regular file: '$filename'.");
    }

    if (!is_readable($filename)) {
        throw new Exception("File '$filename' not readable.");
    }

    $xml = file_get_contents($filename);

    $dom = DOMDocument::loadXML($xml);

    if ($dom->documentElement->namespaceURI != Activity::ATOM ||
        $dom->documentElement->localName != 'feed') {
        throw new Exception("'$filename' is not an Atom feed.");
    }

    return $dom;
}

function importActivityStream($user, $doc)
{
    $feed = $doc->documentElement;

    $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');

    for ($i = $entries->length - 1; $i >= 0; $i--) {
        $entry = $entries->item($i);
        $activity = new Activity($entry, $feed);
        $object = $activity->objects[0];
        if (!have_option('q', 'quiet')) {
            print $activity->content . "\n";
        }
        $html = getTweetHtml($object->link);

        $config = array('safe' => 1,
                        'deny_attribute' => 'class,rel,id,style,on*');

        $html = htmLawed($html, $config);

        $content = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');

        $notice = Notice::saveNew($user->id,
                                  $content,
                                  'importtwitter',
                                  array('uri' => $object->id,
                                        'url' => $object->link,
                                        'rendered' => $html,
                                        'created' => common_sql_date($activity->time),
                                        'replies' => array(),
                                        'groups' => array()));
    }
}

function getTweetHtml($url)
{
    try {
        $client = new HTTPClient();
        $response = $client->get($url);
    } catch (HTTP_Request2_Exception $e) {
        print "ERROR: HTTP response " . $e->getMessage() . "\n";
        return false;
    }

    if (!$response->isOk()) {
        print "ERROR: HTTP response " . $response->getCode() . "\n";
        return false;
    }

    $body = $response->getBody();

    return tweetHtmlFromBody($body);
}

function tweetHtmlFromBody($body)
{
    $doc = DOMDocument::loadHTML($body);
    $xpath = new DOMXPath($doc);

    $spans = $xpath->query('//span[@class="entry-content"]');

    if ($spans->length == 0) {
        print "ERROR: No content in tweet page.\n";
        return '';
    }

    $span = $spans->item(0);

    $children = $span->childNodes;

    $text = '';

    for ($i = 0; $i < $children->length; $i++) {
        $child = $children->item($i);
        if ($child instanceof DOMElement &&
            $child->tagName == 'a' &&
            !preg_match('#^https?://#', $child->getAttribute('href'))) {
            $child->setAttribute('href', 'http://twitter.com' . $child->getAttribute('href'));
        }
        $text .= $doc->saveXML($child);
    }

    return $text;
}

try {

    $doc = getAtomFeedDocument();
    $user = getUser();

    importActivityStream($user, $doc);

} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}


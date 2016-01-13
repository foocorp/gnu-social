<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

/**
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class FeedSubBadURLException extends FeedSubException
{
}

class FeedSubBadResponseException extends FeedSubException
{
}

class FeedSubEmptyException extends FeedSubException
{
}

class FeedSubBadHTMLException extends FeedSubException
{
}

class FeedSubUnrecognizedTypeException extends FeedSubException
{
}

class FeedSubNoFeedException extends FeedSubException
{
}

class FeedSubNoSalmonException extends FeedSubException
{
}

class FeedSubBadXmlException extends FeedSubException
{
}

class FeedSubNoHubException extends FeedSubException
{
}

/**
 * Given a web page or feed URL, discover the final location of the feed
 * and return its current contents.
 *
 * @example
 *   $feed = new FeedDiscovery();
 *   if ($feed->discoverFromURL($url)) {
 *     print $feed->uri;
 *     print $feed->type;
 *     processFeed($feed->feed); // DOMDocument
 *   }
 */
class FeedDiscovery
{
    public $uri;
    public $type;
    public $feed;
    public $root;

    /** Post-initialize query helper... */
    public function getLink($rel, $type=null)
    {
        // @fixme check for non-Atom links in RSS2 feeds as well
        return self::getAtomLink($rel, $type);
    }

    public function getAtomLink($rel, $type=null)
    {
        return ActivityUtils::getLink($this->root, $rel, $type);
    }

    /**
     * Get the referenced PuSH hub link from an Atom feed.
     *
     * @return mixed string or false
     */
    public function getHubLink()
    {
        return $this->getAtomLink('hub');
    }

    /**
     * @param string $url
     * @param bool $htmlOk pass false here if you don't want to follow web pages.
     * @return string with validated URL
     * @throws FeedSubBadURLException
     * @throws FeedSubBadHtmlException
     * @throws FeedSubNoFeedException
     * @throws FeedSubEmptyException
     * @throws FeedSubUnrecognizedTypeException
     */
    function discoverFromURL($url, $htmlOk=true)
    {
        try {
            $client = new HTTPClient();
            $response = $client->get($url);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_ERR, __METHOD__ . " Failure for $url - " . $e->getMessage());
            throw new FeedSubBadURLException($e->getMessage());
        }

        if ($htmlOk) {
            $type = $response->getHeader('Content-Type');
            $isHtml = preg_match('!^(text/html|application/xhtml\+xml)!i', $type);
            if ($isHtml) {
                $target = $this->discoverFromHTML($response->getEffectiveUrl(), $response->getBody());
                if (!$target) {
                    throw new FeedSubNoFeedException($url);
                }
                return $this->discoverFromURL($target, false);
            }
        }

        return $this->initFromResponse($response);
    }

    function discoverFromFeedURL($url)
    {
        return $this->discoverFromURL($url, false);
    }

    function initFromResponse($response)
    {
        if (!$response->isOk()) {
            throw new FeedSubBadResponseException($response->getStatus());
        }

        $sourceurl = $response->getEffectiveUrl();
        $body = $response->getBody();
        if (!$body) {
            throw new FeedSubEmptyException($sourceurl);
        }

        $type = $response->getHeader('Content-Type');
        if (preg_match('!^(text/xml|application/xml|application/(rss|atom)\+xml)!i', $type)) {
            return $this->init($sourceurl, $type, $body);
        } else {
            common_log(LOG_WARNING, "Unrecognized feed type $type for $sourceurl");
            throw new FeedSubUnrecognizedTypeException($type);
        }
    }

    function init($sourceurl, $type, $body)
    {
        $feed = new DOMDocument();
        if ($feed->loadXML($body)) {
            $this->uri = $sourceurl;
            $this->type = $type;
            $this->feed = $feed;

            $el = $this->feed->documentElement;

            // Looking for the "root" element: RSS channel or Atom feed

            if ($el->tagName == 'rss') {
                $channels = $el->getElementsByTagName('channel');
                if ($channels->length > 0) {
                    $this->root = $channels->item(0);
                } else {
                    throw new FeedSubBadXmlException($sourceurl);
                }
            } else if ($el->tagName == 'feed') {
                $this->root = $el;
            } else {
                throw new FeedSubBadXmlException($sourceurl);
            }

            return $this->uri;
        } else {
            throw new FeedSubBadXmlException($sourceurl);
        }
    }

    /**
     * @param string $url source URL, used to resolve relative links
     * @param string $body HTML body text
     * @return mixed string with URL or false if no target found
     */
    function discoverFromHTML($url, $body)
    {
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.
        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok = $dom->loadHTML($body);
        error_reporting($old);

        if (!$ok) {
            throw new FeedSubBadHtmlException();
        }

        // Autodiscovery links may be relative to the page's URL or <base href>
        $base = false;
        $nodes = $dom->getElementsByTagName('base');
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            if ($node->hasAttributes()) {
                $href = $node->attributes->getNamedItem('href');
                if ($href) {
                    $base = trim($href->value);
                }
            }
        }
        if ($base) {
            $base = $this->resolveURI($base, $url);
        } else {
            $base = $url;
        }

        // Ok... now on to the links!
        // Types listed in order of priority -- we'll prefer Atom if available.
        // @fixme merge with the munger link checks
        $feeds = array(
            'application/atom+xml' => false,
            'application/rss+xml' => false,
        );

        $nodes = $dom->getElementsByTagName('link');
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            if ($node->hasAttributes()) {
                $rel = $node->attributes->getNamedItem('rel');
                $type = $node->attributes->getNamedItem('type');
                $href = $node->attributes->getNamedItem('href');
                if ($rel && $type && $href) {
                    $rel = array_filter(explode(" ", $rel->value));
                    $type = trim($type->value);
                    $href = trim($href->value);

                    if (in_array('alternate', $rel) && array_key_exists($type, $feeds) && empty($feeds[$type])) {
                        // Save the first feed found of each type...
                        $feeds[$type] = $this->resolveURI($href, $base);
                    }
                }
            }
        }

        // Return the highest-priority feed found
        foreach ($feeds as $type => $url) {
            if ($url) {
                return $url;
            }
        }

        return false;
    }

    /**
     * Resolve a possibly relative URL against some absolute base URL
     * @param string $rel relative or absolute URL
     * @param string $base absolute URL
     * @return string absolute URL, or original URL if could not be resolved.
     */
    function resolveURI($rel, $base)
    {
        require_once "Net/URL2.php";
        try {
            $relUrl = new Net_URL2($rel);
            if ($relUrl->isAbsolute()) {
                return $rel;
            }
            $baseUrl = new Net_URL2($base);
            $absUrl = $baseUrl->resolve($relUrl);
            return $absUrl->getURL();
        } catch (Exception $e) {
            common_log(LOG_WARNING, 'Unable to resolve relative link "' .
                $rel . '" against base "' . $base . '": ' . $e->getMessage());
            return $rel;
        }
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}


/**
 * Utility class to wrap basic oEmbed lookups.
 *
 * Blacklisted hosts will use an alternate lookup method:
 *  - Twitpic
 *
 * Whitelisted hosts will use known oEmbed API endpoints:
 *  - Flickr, YFrog
 *
 * Sites that provide discovery links will use them directly; a bug
 * in use of discovery links with query strings is worked around.
 *
 * Others will fall back to oohembed (unless disabled).
 * The API endpoint can be configured or disabled through config
 * as 'oohembed'/'endpoint'.
 */
class oEmbedHelper
{
    protected static $apiMap = array(
        'flickr.com' => 'http://www.flickr.com/services/oembed/',
        'yfrog.com' => 'http://www.yfrog.com/api/oembed',
        'youtube.com' => 'http://www.youtube.com/oembed',
        'viddler.com' => 'http://lab.viddler.com/services/oembed/',
        'qik.com' => 'http://qik.com/api/oembed.json',
        'revision3.com' => 'http://revision3.com/api/oembed/',
        'hulu.com' => 'http://www.hulu.com/api/oembed.json',
        'vimeo.com' => 'http://www.vimeo.com/api/oembed.json',
        'my.opera.com' => 'http://my.opera.com/service/oembed',
    );
    protected static $functionMap = array(
        'twitpic.com' => 'oEmbedHelper::twitPic',
    );

    /**
     * Perform or fake an oEmbed lookup for the given resource.
     *
     * Some known hosts are whitelisted with API endpoints where we
     * know they exist but autodiscovery data isn't available.
     * If autodiscovery links are missing and we don't recognize the
     * host, we'll pass it to noembed.com's public service which
     * will either proxy or fake info on a lot of sites.
     *
     * A few hosts are blacklisted due to known problems with oohembed,
     * in which case we'll look up the info another way and return
     * equivalent data.
     *
     * Throws exceptions on failure.
     *
     * @param string $url
     * @param array $params
     * @return object
     */
    public static function getObject($url, $params=array())
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (substr($host, 0, 4) == 'www.') {
            $host = substr($host, 4);
        }

        common_log(LOG_INFO, 'Checking for oEmbed data for ' . $url);

        // You can fiddle with the order of discovery -- either skipping
        // some types or re-ordering them.

        $order = common_config('oembed', 'order');

        foreach ($order as $method) {

            switch ($method) {
            case 'built-in':
                common_log(LOG_INFO, 'Considering built-in oEmbed methods...');
                // Blacklist: systems with no oEmbed API of their own, which are
                // either missing from or broken on noembed.com's proxy.
                // we know how to look data up in another way...
                if (array_key_exists($host, self::$functionMap)) {
                    common_log(LOG_INFO, 'We have a built-in method for ' . $host);
                    $func = self::$functionMap[$host];
                    return call_user_func($func, $url, $params);
                }
                break;
            case 'well-known':
                common_log(LOG_INFO, 'Considering well-known oEmbed endpoints...');
                // Whitelist: known API endpoints for sites that don't provide discovery...
                if (array_key_exists($host, self::$apiMap)) {
                    $api = self::$apiMap[$host];
                    common_log(LOG_INFO, 'Using well-known endpoint "' . $api . '" for "' . $host . '"');
                    break 2;
                }
                break;
            case 'discovery':
                try {
                    common_log(LOG_INFO, 'Trying to discover an oEmbed endpoint using link headers.');
                    $api = self::discover($url);
                    common_log(LOG_INFO, 'Found API endpoint ' . $api . ' for URL ' . $url);
                    break 2;
                } catch (Exception $e) {
                    common_log(LOG_INFO, 'Could not find an oEmbed endpoint using link headers.');
                    // Just ignore it!
                }
                break;
            case 'service':
                $api = common_config('oembed', 'endpoint');
                common_log(LOG_INFO, 'Using service API endpoint ' . $api);
                break 2;
                break;
            }
        }

        if (empty($api)) {
            // TRANS: Server exception thrown in oEmbed action if no API endpoint is available.
            throw new ServerException(_('No oEmbed API endpoint available.'));
        }

        return self::getObjectFrom($api, $url, $params);
    }

    /**
     * Perform basic discovery.
     * @return string
     */
    static function discover($url)
    {
        // @fixme ideally skip this for non-HTML stuff!
        $body = self::http($url);
        return self::discoverFromHTML($url, $body);
    }

    /**
     * Partially ripped from OStatus' FeedDiscovery class.
     *
     * @param string $url source URL, used to resolve relative links
     * @param string $body HTML body text
     * @return mixed string with URL or false if no target found
     */
    static function discoverFromHTML($url, $body)
    {
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.
        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok = $dom->loadHTML($body);
        error_reporting($old);

        if (!$ok) {
            throw new oEmbedHelper_BadHtmlException();
        }

        // Ok... now on to the links!
        $feeds = array(
            'application/json+oembed' => false,
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
                        $feeds[$type] = $href;
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

        throw new oEmbedHelper_DiscoveryException();
    }

    /**
     * Actually do an oEmbed lookup to a particular API endpoint.
     *
     * @param string $api oEmbed API endpoint URL
     * @param string $url target URL to look up info about
     * @param array $params
     * @return object
     */
    static function getObjectFrom($api, $url, $params=array())
    {
        $params['url'] = $url;
        $params['format'] = 'json';
        $key=common_config('oembed','apikey');
        if(isset($key)) {
            $params['key'] = common_config('oembed','apikey');
        }
        $data = self::json($api, $params);
        return self::normalize($data);
    }

    /**
     * Normalize oEmbed format.
     *
     * @param object $orig
     * @return object
     */
    static function normalize($orig)
    {
        $data = clone($orig);

        if (empty($data->type)) {
            throw new Exception('Invalid oEmbed data: no type field.');
        }

        if ($data->type == 'image') {
            // YFrog does this.
            $data->type = 'photo';
        }

        if (isset($data->thumbnail_url)) {
            if (!isset($data->thumbnail_width)) {
                // !?!?!
                $data->thumbnail_width = common_config('thumbnail', 'width');
                $data->thumbnail_height = common_config('thumbnail', 'height');
            }
        }

        return $data;
    }

    /**
     * Using a local function for twitpic lookups, as oohembed's adapter
     * doesn't return a valid result:
     * http://code.google.com/p/oohembed/issues/detail?id=19
     *
     * This code fetches metadata from Twitpic's own API, and attempts
     * to guess proper thumbnail size from the original's size.
     *
     * @todo respect maxwidth and maxheight params
     *
     * @param string $url
     * @param array $params
     * @return object
     */
    static function twitPic($url, $params=array())
    {
        $matches = array();
        if (preg_match('!twitpic\.com/(\w+)!', $url, $matches)) {
            $id = $matches[1];
        } else {
            throw new Exception("Invalid twitpic URL");
        }

        // Grab metadata from twitpic's API...
        // http://dev.twitpic.com/docs/2/media_show
        $data = self::json('http://api.twitpic.com/2/media/show.json',
                array('id' => $id));
        $oembed = (object)array('type' => 'photo',
                                'url' => 'http://twitpic.com/show/full/' . $data->short_id,
                                'width' => $data->width,
                                'height' => $data->height);
        if (!empty($data->message)) {
            $oembed->title = $data->message;
        }

        // Thumbnail is cropped and scaled to 150x150 box:
        // http://dev.twitpic.com/docs/thumbnails/
        $thumbSize = 150;
        $oembed->thumbnail_url = 'http://twitpic.com/show/thumb/' . $data->short_id;
        $oembed->thumbnail_width = $thumbSize;
        $oembed->thumbnail_height = $thumbSize;

        return $oembed;
    }

    /**
     * Fetch some URL and return JSON data.
     *
     * @param string $url
     * @param array $params query-string params
     * @return object
     */
    static protected function json($url, $params=array())
    {
        $data = self::http($url, $params);
        return json_decode($data);
    }

    /**
     * Hit some web API and return data on success.
     * @param string $url
     * @param array $params
     * @return string
     */
    static protected function http($url, $params=array())
    {
        $client = HTTPClient::start();
        if ($params) {
            $query = http_build_query($params, null, '&');
            if (strpos($url, '?') === false) {
                $url .= '?' . $query;
            } else {
                $url .= '&' . $query;
            }
        }
        $response = $client->get($url);
        if ($response->isOk()) {
            return $response->getBody();
        } else {
            throw new Exception('Bad HTTP response code: ' . $response->getStatus());
        }
    }
}

class oEmbedHelper_Exception extends Exception
{
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code);
    }
}

class oEmbedHelper_BadHtmlException extends oEmbedHelper_Exception
{
    function __construct($previous=null)
    {
        return parent::__construct('Bad HTML in discovery data.', 0, $previous);
    }
}

class oEmbedHelper_DiscoveryException extends oEmbedHelper_Exception
{
    function __construct($previous=null)
    {
        return parent::__construct('No oEmbed discovery data.', 0, $previous);
    }
}

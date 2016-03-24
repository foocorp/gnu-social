<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to do linkbacks for notices containing links
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once(__DIR__ . '/lib/util.php');

define('LINKBACKPLUGIN_VERSION', '0.1');

/**
 * Plugin to do linkbacks for notices containing URLs
 *
 * After new notices are saved, we check their text for URLs. If there
 * are URLs, we test each URL to see if it supports any
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */
class LinkbackPlugin extends Plugin
{
    var $notice = null;

    function __construct()
    {
        parent::__construct();
    }

    function onHandleQueuedNotice(Notice $notice)
    {
        if (!$notice->isLocal() || !$notice->isPublic()) {
            return true;
        }

        // Try to avoid actually mucking with the
        // notice content
        $c = $notice->content;
        $this->notice = $notice;

        if (!$notice->getProfile()->getPref('linkbackplugin', 'disable_linkbacks')) {
            // Ignoring results
            common_replace_urls_callback($c, array($this, 'linkbackUrl'));
        }

        try {
            if ($notice->isRepeat()) {
                $repeat = Notice::getByID($notice->repeat_of);
                $this->linkbackUrl($repeat->getUrl());
            } elseif (!empty($notice->reply_to)) {
                $parent = $notice->getParent();
                $this->linkbackUrl($parent->getUrl());
            }
        } catch (InvalidUrlException $e) {
            // can't send linkback to notice if we don't have a remote HTTP(S) URL
            // but we can still ping the attention-receivers below
        } catch (NoParentNoticeException $e) {
            // can't send linkback to non-existing parent URL
            return true;
        }

        // doubling up getReplies and getAttentionProfileIDs because we're not entirely migrated yet
        $replyProfiles = Profile::multiGet('id', array_unique(array_merge($notice->getReplies(), $notice->getAttentionProfileIDs())));
        foreach ($replyProfiles->fetchAll('profileurl') as $profileurl) {
            if (common_valid_http_url($profileurl)) {
                $this->linkbackUrl($profileurl);
            }
        }

        return true;
    }

    function linkbackUrl($url)
    {
        common_log(LOG_DEBUG,"Attempting linkback for " . $url);

        $orig = $url;
        $url = htmlspecialchars_decode($orig);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'))) {
            return $orig;
        }

        // XXX: Do a HEAD first to save some time/bandwidth
        try {
            $httpclient = new HTTPClient();
            $response = $httpclient->get($url, ["User-Agent: {$this->userAgent()}",
                                                "Accept: application/html+xml,text/html"]);

            if (!in_array($response->getStatus(), array(200, 206))) {
                throw new Exception('Invalid response code for GET request');
            }
        } catch (Exception $e) {
            // something didn't work out in our GET request
            return $orig;
        }

        // XXX: Should handle relative-URI resolution in these detections

        $wm = $this->getWebmention($response);
        if(!empty($wm)) {
            // It is the webmention receiver's job to resolve source
            // Ref: https://github.com/converspace/webmention/issues/43
            $this->webmention($url, $wm);
        } else {
            $pb = $this->getPingback($response);
            if (!empty($pb)) {
                // Pingback still looks for exact URL in our source, so we
                // must send what we have
                $this->pingback($url, $pb);
            } else {
                $tb = $this->getTrackback($response);
                if (!empty($tb)) {
                    $this->trackback($response->getEffectiveUrl(), $tb);
                }
            }
        }

        return $orig;
    }

    // Based on https://github.com/indieweb/mention-client-php
    // which is licensed Apache 2.0
    function getWebmention(HTTP_Request2_Response $response) {
        $link = $response->getHeader('Link');
        if (!is_null($link)) {
            // XXX: the fetcher gives back a comma-separated string of all Link headers, I hope the parsing works reliably
            if (preg_match('~<((?:https?://)?[^>]+)>; rel="webmention"~', $link, $match)) {
                return $match[1];
            } elseif (preg_match('~<((?:https?://)?[^>]+)>; rel="http://webmention.org/?"~', $link, $match)) {
                return $match[1];
            }
        }

        // FIXME: Do proper DOM traversal
        if(preg_match('/<(?:link|a)[ ]+href="([^"]+)"[ ]+rel="[^" ]* ?webmention ?[^" ]*"[ ]*\/?>/i', $response->getBody(), $match)
                || preg_match('/<(?:link|a)[ ]+rel="[^" ]* ?webmention ?[^" ]*"[ ]+href="([^"]+)"[ ]*\/?>/i', $response->getBody(), $match)) {
            return $match[1];
        } elseif (preg_match('/<(?:link|a)[ ]+href="([^"]+)"[ ]+rel="http:\/\/webmention\.org\/?"[ ]*\/?>/i', $response->getBody(), $match)
                || preg_match('/<(?:link|a)[ ]+rel="http:\/\/webmention\.org\/?"[ ]+href="([^"]+)"[ ]*\/?>/i', $response->getBody(), $match)) {
            return $match[1];
        }
    }

    function webmention($url, $endpoint) {
        $source = $this->notice->getUrl();

        $payload = array(
            'source' => $source,
            'target' => $url
        );

        $request = HTTPClient::start();
        try {
            $response = $request->post($endpoint,
                array(
                    'Content-type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ),
                $payload
            );

            if(!in_array($response->getStatus(), array(200,202))) {
                common_log(LOG_WARNING,
                           "Webmention request failed for '$url' ($endpoint)");
            }
        } catch (Exception $e) {
            common_log(LOG_WARNING, "Webmention request failed for '{$url}' ({$endpoint}): {$e->getMessage()}");
        }
    }

    function getPingback(HTTP_Request2_Response $response) {
        if ($response->getHeader('X-Pingback')) {
            return $response->getHeader('X-Pingback');
        } elseif (preg_match('/<(?:link|a)[ ]+href="([^"]+)"[ ]+rel="[^" ]* ?pingback ?[^" ]*"[ ]*\/?>/i', $response->getBody(), $match)
                || preg_match('/<(?:link|a)[ ]+rel="[^" ]* ?pingback ?[^" ]*"[ ]+href="([^"]+)"[ ]*\/?>/i', $response->getBody(), $match)) {
            return $match[1];
        }
    }

    function pingback($url, $endpoint)
    {
        $args = array($this->notice->getUrl(), $url);

        if (!extension_loaded('xmlrpc')) {
            if (!dl('xmlrpc.so')) {
                common_log(LOG_ERR, "Can't pingback; xmlrpc extension not available.");
                return;
            }
        }

        $request = HTTPClient::start();
        try {
            $request->setBody(xmlrpc_encode_request('pingback.ping', $args));
            $response = $request->post($endpoint,
                array('Content-Type: text/xml'),
                false);
            $response = xmlrpc_decode($response->getBody());
            if (xmlrpc_is_fault($response)) {
                common_log(LOG_WARNING,
                       "Pingback error for '$url' ($endpoint): ".
                       "$response[faultString] ($response[faultCode])");
            } else {
                common_log(LOG_INFO,
                       "Pingback success for '$url' ($endpoint): ".
                       "'$response'");
            }
        } catch (Exception $e) {
            common_log(LOG_WARNING, "Pingback request failed for '{$url}' ({$endpoint}): {$e->getMessage()}");
        }
    }

    // Largely cadged from trackback_cls.php by
    // Ran Aroussi <ran@blogish.org>, GPL2 or any later version
    // http://phptrackback.sourceforge.net/
    function getTrackback(HTTP_Request2_Response $response)
    {
        $text = $response->getBody();
        $url = $response->getEffectiveUrl();

        if (preg_match_all('/(<rdf:RDF.*?<\/rdf:RDF>)/sm', $text, $match, PREG_SET_ORDER)) {
            for ($i = 0; $i < count($match); $i++) {
                if (preg_match('|dc:identifier="' . preg_quote($url) . '"|ms', $match[$i][1])) {
                    $rdf_array[] = trim($match[$i][1]);
                }
            }

            // Loop through the RDFs array and extract trackback URIs

            $tb_array = array(); // <- holds list of trackback URIs

            if (!empty($rdf_array)) {

                for ($i = 0; $i < count($rdf_array); $i++) {
                    if (preg_match('/trackback:ping="([^"]+)"/', $rdf_array[$i], $array)) {
                        $tb_array[] = trim($array[1]);
                        break;
                    }
                }
            }

            // Return Trackbacks

            if (empty($tb_array)) {
                return null;
            } else {
                return $tb_array[0];
            }
        }

        if (preg_match_all('/(<a[^>]*?rel=[\'"]trackback[\'"][^>]*?>)/', $text, $match)) {
            foreach ($match[1] as $atag) {
                if (preg_match('/href=[\'"]([^\'"]*?)[\'"]/', $atag, $url)) {
                    return $url[1];
                }
            }
        }

        return null;

    }

    function trackback($url, $endpoint)
    {
        $profile = $this->notice->getProfile();

        // TRANS: Trackback title.
        // TRANS: %1$s is a profile nickname, %2$s is a timestamp.
        $args = array('title' => sprintf(_m('%1$s\'s status on %2$s'),
                                         $profile->getNickname(),
                                         common_exact_date($this->notice->getCreated())),
                      'excerpt' => $this->notice->getContent(),
                      'url' => $this->notice->getUrl(),
                      'blog_name' => $profile->getNickname());

        try {
            $httpclient = new HTTPClient(null, HTTPClient::METHOD_POST);
            $response = $httpclient->post($endpoint, ["User-Agent: {$this->userAgent()}"], $args);
            if ($response->getStatus() === 200) {
                common_log(LOG_INFO, "Trackback success for '$url' ($endpoint): "._ve($response->getBody()));
            } else {
                common_log(LOG_WARNING, "Trackback error for '$url' ($endpoint): "._ve($response->getBody()));
            }
        } catch (Exception $e) {
            common_log(LOG_INFO, "Trackback error for '$url' ($endpoint): "._ve($e->getMessage()));
        }
    }


    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('main/linkback/webmention', array('action' => 'webmention'));
        $m->connect('main/linkback/pingback', array('action' => 'pingback'));
    }

    public function onStartShowHTML($action)
    {
        header('Link: <' . common_local_url('webmention') . '>; rel="webmention"', false);
        header('X-Pingback: ' . common_local_url('pingback'));
    }

    public function version()
    {
        return LINKBACKPLUGIN_VERSION;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Linkback',
                            'version' => LINKBACKPLUGIN_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Linkback',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Notify blog authors when their posts have been linked in '.
                               'microblog notices using '.
                               '<a href="http://www.hixie.ch/specs/pingback/pingback">Pingback</a> '.
                               'or <a href="http://www.movabletype.org/docs/mttrackback.html">Trackback</a> protocols.'));
        return true;
    }

    public function onStartInitializeRouter(URLMapper $m)
    {
        $m->connect('settings/linkback', array('action' => 'linkbacksettings'));
        return true;
    }

    function onEndAccountSettingsNav($action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('linkbacksettings'),
                          // TRANS: OpenID plugin menu item on user settings page.
                          _m('MENU', 'Send Linkbacks'),
                          // TRANS: OpenID plugin tooltip for user settings menu item.
                          _m('Opt-out of sending linkbacks.'),
                          $action_name === 'linkbacksettings');
        return true;
    }

    function onStartNoticeSourceLink($notice, &$name, &$url, &$title)
    {
        // If we don't handle this, keep the event handler going
        if (!in_array($notice->source, array('linkback'))) {
            return true;
        }

        try {
            $url = $notice->getUrl();
            // If getUrl() throws exception, $url is never set

            $bits = parse_url($url);
            $domain = $bits['host'];
            if (substr($domain, 0, 4) == 'www.') {
                $name = substr($domain, 4);
            } else {
                $name = $domain;
            }

            // TRANS: Title. %s is a domain name.
            $title = sprintf(_m('Sent from %s via Linkback'), $domain);

            // Abort event handler, we have a name and URL!
            return false;
        } catch (InvalidUrlException $e) {
            // This just means we don't have the notice source data
            return true;
        }
    }
}

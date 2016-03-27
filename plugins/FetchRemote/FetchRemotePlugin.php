<?php
/*
 * GNU social - a federating social network
 * Copyright (C) 2013-2014, Free Software Foundation, Inc.
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
 * Uses WebFinger to implement remote notice retrieval for GNU social.
 *
 * Depends on: WebFinger plugin
 *
 * @package GNUsocial
 * @author  Mikael Nordfeldth <mmn@hethane.se>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class FetchRemotePlugin extends Plugin
{
    static function fetchNoticeFromUrl($url)
    {
        if (!common_valid_http_url($url)) {
            throw new InvalidUrlException($url);
        }
        $host = parse_url($url, PHP_URL_HOST);

        // TODO: try to fetch directly, either by requesting Atom or
        // Link headers/<head> elements with rel=alternate and compare
        // the remote domain name with the notice URL's.

        if (!$stored instanceof Notice) {
            common_log(LOG_INFO, 'Could not fetch remote notice from URL: '._ve($url));
            throw new ServerException('Could not fetch remote notice.');
        }
        return $stored;
    }

    public function onFetchRemoteNoticeWithSource($uri, Profile $source, &$stored)
    {
        if (common_valid_http_url($uri) && !Event::handle('FetchRemoteNoticeFromUrl', array($url, &$stored))) {
            // Woopi, we got it straight from a URL-formatted URI!
            return false;
        }

        // Let's assume we can only do this over HTTPS and a proper
        // WebFinger (RFC7033) endpoint on /.well-known/webfinger
        try {
            $source_url = parse_url($source->getUrl());
        } catch (InvalidUrlException $e) {
            return true;
        }
        if ($source_url['scheme'] !== 'https') {
            common_debug('Will not try to fetch remote notice from non-HTTPS capable profile source');
            return true;
        }

        try {
            $port    = isset($source_url['port']) ? ":{$source_url['port']}" : '';
            $rfc7033 = "https://{$source_url['host']}{$port}/.well-known/webfinger";
            $params  = ['resource' => $uri];
            common_debug(__METHOD__ . ": getting json data about notice from: {$rfc7033}?resource=$uri");
            $json    = HTTPClient::quickGetJson($rfc7033, $params);
        } catch (Exception $e) {
            // NOPE NOPE NOPE NOPE
            // couldn't get remote data about this notice's URI
            // FIXME: try later?
            return true;
        }

        if (!isset($json->aliases)) {
           // FIXME: malformed json for our current use, but maybe we could find rel="alternate" type="text/html"?
           return true;
        }

        common_debug(__METHOD__ . ": Found these aliases: "._ve($json->aliases));
        foreach ($json->aliases as $alias) {
            try {
                $stored = self::fetchNoticeFromUrl($url);
                if ($stored instanceof Notice) {
                    // we're done here! all good, let's get back to business
                    return false;
                }
            } catch (InvalidUrlException $e) {
                /// mmmmye, aliases might not always be HTTP(S) URLs.
            } catch (Exception $e) {
                // oh well, try the next one and see if it works better.
                common_debug(__METHOD__ . ": {$e->getMessage()}");
            }
        }

        // Nothing found, return true to continue processing the event
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'FetchRemote',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://www.gnu.org/software/social/',
                            // TRANS: Plugin description.
                            'rawdescription' => _m('Retrieves remote notices (and serves local) via WebFinger'));

        return true;
    }
}

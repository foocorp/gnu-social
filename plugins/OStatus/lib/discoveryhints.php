<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Some utilities for generating hint data
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

class DiscoveryHints {
    static function fromXRD(XML_XRD $xrd)
    {
        $hints = array();

        foreach ($xrd->links as $link) {
            switch ($link->rel) {
            case WebFingerResource_Profile::PROFILEPAGE:
                $hints['profileurl'] = $link->href;
                break;
            case Salmon::REL_SALMON:
            case Salmon::NS_MENTIONS:   // XXX: deprecated, remove in the future
            case Salmon::NS_REPLIES:    // XXX: deprecated, remove in the future
                $hints['salmon'] = $link->href;
                break;
            case Discovery::UPDATESFROM:
                if (empty($link->type) || $link->type == 'application/atom+xml') {
                    $hints['feedurl'] = $link->href;
                }
                break;
            case Discovery::HCARD:
                $hints['hcardurl'] = $link->href;
                break;
            default:
                break;
            }
        }

        return $hints;
    }

    static function fromHcardUrl($url)
    {
        $client = new HTTPClient();
        $client->setHeader('Accept', 'text/html,application/xhtml+xml');
        $response = $client->get($url);

        if (!$response->isOk()) {
            return null;
        }

        return self::hcardHints($response->getBody(),
                                $response->getUrl());
    }

    static function hcardHints($body, $url)
    {
        $hcard = self::_hcard($body, $url);

        if (empty($hcard)) {
            return array();
        }

        $hints = array();

        // XXX: don't copy stuff into an array and then copy it again

        if (array_key_exists('nickname', $hcard)) {
            $hints['nickname'] = $hcard['nickname'];
        }

        if (array_key_exists('fn', $hcard)) {
            $hints['fullname'] = $hcard['fn'];
        } else if (array_key_exists('n', $hcard)) {
            $hints['fullname'] = implode(' ', $hcard['n']);
        }

        if (array_key_exists('photo', $hcard) && count($hcard['photo'])) {
            $hints['avatar'] = $hcard['photo'][0];
        }

        if (array_key_exists('note', $hcard)) {
            $hints['bio'] = $hcard['note'];
        }

        if (array_key_exists('adr', $hcard)) {
            if (is_string($hcard['adr'])) {
                $hints['location'] = $hcard['adr'];
            } else if (is_array($hcard['adr'])) {
                $hints['location'] = implode(' ', $hcard['adr']);
            }
        }

        if (array_key_exists('url', $hcard)) {
            if (is_string($hcard['url'])) {
                $hints['homepage'] = $hcard['url'];
            } else if (is_array($hcard['url']) && !empty($hcard['url'])) {
                // HACK get the last one; that's how our hcards look
                $hints['homepage'] = $hcard['url'][count($hcard['url'])-1];
            }
        }

        return $hints;
    }

    static function _hcard($body, $url)
    {
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.

        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));

        $doc = new DOMDocument();
        $doc->loadHTML($body);

        error_reporting($old);

        $xp = new DOMXPath($doc);

        $hcardNodes = self::_getChildrenByClass($doc->documentElement, 'vcard', $xp);

        $hcards = array();

        for ($i = 0; $i < $hcardNodes->length; $i++) {

            $hcardNode = $hcardNodes->item($i);

            $hcard = self::_hcardFromNode($hcardNode, $xp, $url);

            $hcards[] = $hcard;
        }

        $repr = null;

        foreach ($hcards as $hcard) {
            if (in_array($url, $hcard['url'])) {
                $repr = $hcard;
                break;
            }
        }

        if (!is_null($repr)) {
            return $repr;
        } else if (count($hcards) > 0) {
            return $hcards[0];
        } else {
            return null;
        }
    }

    function _getChildrenByClass($el, $cls, $xp)
    {
        // borrowed from hkit. Thanks dudes!

        $qry = ".//*[contains(concat(' ',normalize-space(@class),' '),' $cls ')]";

        $nodes = $xp->query($qry, $el);

        return $nodes;
    }

    function _hcardFromNode($hcardNode, $xp, $base)
    {
        $hcard = array();

        $hcard['url'] = array();

        $urlNodes = self::_getChildrenByClass($hcardNode, 'url', $xp);

        for ($j = 0; $j < $urlNodes->length; $j++) {

            $urlNode = $urlNodes->item($j);

            if ($urlNode->hasAttribute('href')) {
                $url = $urlNode->getAttribute('href');
            } else {
                $url = $urlNode->textContent;
            }

            $hcard['url'][] = self::_rel2abs($url, $base);
        }

        $hcard['photo'] = array();

        $photoNodes = self::_getChildrenByClass($hcardNode, 'photo', $xp);

        for ($j = 0; $j < $photoNodes->length; $j++) {
            $photoNode = $photoNodes->item($j);
            if ($photoNode->hasAttribute('src')) {
                $url = $photoNode->getAttribute('src');
            } else if ($photoNode->hasAttribute('href')) {
                $url = $photoNode->getAttribute('href');
            } else {
                $url = $photoNode->textContent;
            }
            $hcard['photo'][] = self::_rel2abs($url, $base);
        }

        $singles = array('nickname', 'note', 'fn', 'n', 'adr');

        foreach ($singles as $single) {

            $nodes = self::_getChildrenByClass($hcardNode, $single, $xp);

            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $hcard[$single] = $node->textContent;
            }
        }

        return $hcard;
    }

    // XXX: this is a first pass; we probably need
    // to handle things like ../ and ./ and so on

    static function _rel2abs($rel, $wrt)
    {
        $parts = parse_url($rel);

        if ($parts === false) {
            return false;
        }

        // If it's got a scheme, use it

        if (!empty($parts['scheme'])) {
            return $rel;
        }

        $w = parse_url($wrt);

        $base = $w['scheme'].'://'.$w['host'];

        if ($rel[0] == '/') {
            return $base.$rel;
        }

        $wp = explode('/', $w['path']);

        array_pop($wp);

        return $base.implode('/', $wp).'/'.$rel;
    }
}

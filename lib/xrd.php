<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
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
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class XRD
{
    const XML_NS = 'http://www.w3.org/2000/xmlns/';

    const XRD_NS = 'http://docs.oasis-open.org/ns/xri/xrd-1.0';

    const HOST_META_NS = 'http://host-meta.net/xrd/1.0';

    public $expires;

    public $subject;

    public $host;

    public $alias = array();

    public $types = array();

    public $links = array();

    public static function parse($xml)
    {
        $xrd = new XRD();

        $dom = new DOMDocument();

        // Don't spew XML warnings to output
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        $ok = $dom->loadXML($xml);
        error_reporting($old);

        if (!$ok) {
            // TRANS: Exception.
            throw new Exception(_('Invalid XML.'));
        }
        $xrd_element = $dom->getElementsByTagName('XRD')->item(0);
        if (!$xrd_element) {
            // TRANS: Exception.
            throw new Exception(_('Invalid XML, missing XRD root.'));
        }

        // Check for host-meta host
        $host = $xrd_element->getElementsByTagName('Host')->item(0);
        if ($host) {
            $xrd->host = $host->nodeValue;
        }

        // Loop through other elements
        foreach ($xrd_element->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            switch ($node->tagName) {
            case 'Expires':
                $xrd->expires = $node->nodeValue;
                break;
            case 'Subject':
                $xrd->subject = $node->nodeValue;
                break;

            case 'Alias':
                $xrd->alias[] = $node->nodeValue;
                break;

            case 'Link':
                $xrd->links[] = $xrd->parseLink($node);
                break;

            case 'Type':
                $xrd->types[] = $xrd->parseType($node);
                break;

            }
        }
        return $xrd;
    }

    public function toXML()
    {
        $xs = new XMLStringer();

        $xs->startXML();
        $xs->elementStart('XRD', array('xmlns' => XRD::XRD_NS));

        if ($this->host) {
            $xs->element('hm:Host', array('xmlns:hm' => XRD::HOST_META_NS), $this->host);
        }

        if ($this->expires) {
            $xs->element('Expires', null, $this->expires);
        }

        if ($this->subject) {
            $xs->element('Subject', null, $this->subject);
        }

        foreach ($this->alias as $alias) {
            $xs->element('Alias', null, $alias);
        }

        foreach ($this->links as $link) {
            $titles = array();
            $properties = array();
            if (isset($link['title'])) {
                $titles = $link['title'];
                unset($link['title']);
            }
            if (isset($link['property'])) {
                $properties = $link['property'];
                unset($link['property']);
            }
            $xs->elementStart('Link', $link);
            foreach ($titles as $title) {
                $xs->element('Title', null, $title);
            }
            foreach ($properties as $property) {
                $xs->element('Property',
                             array('type' => $property['type']),
                             $property['value']);
            }
            $xs->elementEnd('Link');
        }

        $xs->elementEnd('XRD');

        return $xs->getString();
    }

    function parseType($element)
    {
        return array();
    }

    function parseLink($element)
    {
        $link = array();
        $link['rel'] = $element->getAttribute('rel');
        $link['type'] = $element->getAttribute('type');
        $link['href'] = $element->getAttribute('href');
        $link['template'] = $element->getAttribute('template');
        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement) {
                switch($node->tagName) {
                case 'Title':
                    $link['title'][] = $node->nodeValue;
                    break;
                case 'Property':
                    $link['property'][] = array('type' => $node->getAttribute('type'),
                                                'value' => $node->nodeValue);
                    break;
                default:
                    common_log(LOG_NOTICE, "Unexpected tag name {$node->tagName} found in XRD file.");
                }
            }
        }

        return $link;
    }
}

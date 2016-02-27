<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Data structure for info about syndication feeds (RSS 1.0, RSS 2.0, Atom)
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Data structure for feeds
 *
 * This structure is a helpful container for shipping around information about syndication feeds.
 *
 * @category Feed
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Feed
{
    const RSS1 = 1;
    const RSS2 = 2;
    const ATOM = 3;
    const FOAF = 4;
    const JSON = 5; // Activity Streams

    var $type = null;
    var $url = null;
    var $title = null;

    function __construct($type, $url, $title)
    {
        $this->type  = $type;
        $this->url   = $url;
        $this->title = $title;
    }

    function getUrl()
    {
        return $this->url;
    }

    function mimeType()
    {
        switch ($this->type) {
         case Feed::RSS1:
            return 'application/rdf+xml';
         case Feed::RSS2:
            return 'application/rss+xml';
         case Feed::ATOM:
            return 'application/atom+xml';
         case Feed::FOAF:
            return 'application/rdf+xml';
         case Feed::JSON:
            return 'application/stream+json';
         default:
            return null;
        }
    }

    function typeName()
    {
        switch ($this->type) {
         case Feed::RSS1:
            // TRANS: Feed type name.
            return _('RSS 1.0');
         case Feed::RSS2:
            // TRANS: Feed type name.
            return _('RSS 2.0');
         case Feed::ATOM:
            // TRANS: Feed type name.
            return _('Atom');
         case Feed::FOAF:
            // TRANS: Feed type name. FOAF stands for Friend of a Friend.
            return _('FOAF');
         case Feed::JSON:
            // TRANS: Feed type name. See http://activitystrea.ms/
            return _('Activity Streams');
         default:
            return null;
        }
    }

    function rel()
    {
        switch ($this->type) {
         case Feed::RSS1:
         case Feed::RSS2:
         case Feed::ATOM:
         case Feed::JSON:
            return 'alternate';
         case Feed::FOAF:
            return 'meta';
         default:
            return null;
        }
    }
}

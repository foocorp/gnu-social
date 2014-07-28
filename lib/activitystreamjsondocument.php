<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for serializing Activity Streams in JSON
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET'))
{
    exit(1);
}

/**
 * A class for generating JSON documents that represent an Activity Streams
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ActivityStreamJSONDocument extends JSONActivityCollection
{
    // Note: Lot of AS folks think the content type should be:
    // 'application/stream+json; charset=utf-8', but this is more
    // useful at the moment, because some programs actually understand
    // it.
    const CONTENT_TYPE = 'application/json; charset=utf-8';

    /* Top level array representing the document */
    protected $doc = array();

    /* The current authenticated user */
    protected $cur;
    protected $scoped = null;

    /* Title of the document */
    protected $title;

    /* Links associated with this document */
    protected $links;

    /* Count of items in this document */
    // XXX This is cryptically referred to in the spec: "The Stream serialization MAY contain a count property."
    protected $count;

    /**
     * Constructor
     *
     * @param User $cur the current authenticated user
     */

    function __construct($cur = null, $title = null, $items = null, $links = null, $url = null)
    {
        parent::__construct($items, $url);

        $this->cur = $cur ?: common_current_user();
        $this->scoped = !is_null($this->cur) ? $this->cur->getProfile() : null;

        /* Title of the JSON document */
        $this->title = $title;

        if (!empty($items)) {
            $this->count = count($this->items);
        }

        /* Array of links associated with the document */
        $this->links = empty($links) ? array() : $items;

        /* URL of a document, this document? containing a list of all the items in the stream */
        if (!empty($this->url)) {
            $this->url = $this->url;
        }
    }

    /**
     * Set the title of the document
     *
     * @param String $title the title
     */

    function setTitle($title)
    {
        $this->title = $title;
    }

    function setUrl($url)
    {
        $this->url = $url;
    }


    /**
     * Add more than one Item to the document
     *
     * @param mixed $notices an array of Notice objects or handle
     *
     */

    function addItemsFromNotices($notices)
    {
        if (is_array($notices)) {
            foreach ($notices as $notice) {
                $this->addItemFromNotice($notice);
            }
        } else {
            while ($notices->fetch()) {
                $this->addItemFromNotice($notices);
            }
        }
    }

    /**
     * Add a single Notice to the document
     *
     * @param Notice $notice a Notice to add
     */

    function addItemFromNotice($notice)
    {
        $act          = $notice->asActivity($this->scoped);
        $act->extra[] = $notice->noticeInfo($this->scoped);
        array_push($this->items, $act->asArray());
        $this->count++;
    }

    /**
     * Add a link to the JSON document
     *
     * @param string $url the URL for the link
     * @param string $rel the link relationship
     */
    function addLink($url = null, $rel = null, $mediaType = null)
    {
        $link = new ActivityStreamsLink($url, $rel, $mediaType);
        array_push($this->links, $link->asArray());
    }

    /*
     * Return the entire document as a big string of JSON
     *
     * @return string encoded JSON output
     */
    function asString()
    {
        $this->doc['generator'] = 'GNU social ' . GNUSOCIAL_VERSION; // extension
        $this->doc['title'] = $this->title;
        $this->doc['url']   = $this->url;
        $this->doc['totalItems'] = $this->count;
        $this->doc['items'] = $this->items;
        $this->doc['links'] = $this->links; // extension
        return json_encode(array_filter($this->doc)); // filter out empty elements
    }

}

/**
 * A class for representing MediaLinks in JSON Activities
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ActivityStreamsMediaLink extends ActivityStreamsLink
{
    private $linkDict;

    function __construct(
        $url       = null,
        $width     = null,
        $height    = null,
        $mediaType = null, // extension
        $rel       = null, // extension
        $duration  = null
    )
    {
        parent::__construct($url, $rel, $mediaType);
        $this->linkDict = array(
            'width'      => intval($width),
            'height'     => intval($height),
            'duration'   => intval($duration)
        );
    }

    function asArray()
    {
        return array_merge(
            parent::asArray(),
            array_filter($this->linkDict)
        );
    }
}

/*
 * Collection primarily as the root of an Activity Streams doc but can be used as the value
 * of extension properties in a variety of situations.
 *
 * A valid Collection object serialization MUST contain at least the url or items properties.
 */
class JSONActivityCollection {

    /* Non-negative integer specifying the total number of activities within the stream */
    protected $totalItems;

    /* An array containing a listing of Objects of any object type */
    protected $items;

    /* IRI referencing a JSON document containing the full listing of objects in the collection */
    protected $url;

    /**
     * Constructor
     *
     * @param array  $items       array of activity items
     * @param string $url         url of a doc list all the objs in the collection
     * @param int    $totalItems  total number of items in the collection
     */
    function __construct($items = null, $url = null)
    {
        $this->items      = empty($items) ? array() : $items;
        $this->totalItems = count($items);
        $this->url        = $url;
    }

    /**
     * Get the total number of items in the collection
     *
     * @return int total the total
     */
    public function getTotalItems()
    {
        $this->totalItems = count($items);
        return $this->totalItems;
    }
}

/**
 * A class for representing links in JSON Activities
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ActivityStreamsLink
{
    private $linkDict;

    function __construct($url = null, $rel = null, $mediaType = null)
    {
        // links MUST have a URL
        if (empty($url)) {
            throw new Exception('Links must have a URL.');
        }

        $this->linkDict = array(
            'url'   => $url,
            'rel'   => $rel,      // extension
            'type'  => $mediaType // extension
        );
    }

    function asArray()
    {
        return array_filter($this->linkDict);
    }
}


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
class ActivityStreamJSONDocument
{
    const CONTENT_TYPE = 'application/stream+json; charset=utf-8';

    /* Top level array representing the document */
    protected $doc = array();

    /* The current authenticated user */
    protected $cur = null;

    /**
     * Constructor
     *
     * @param User $cur the current authenticated user
     */

    function __construct($cur = null)
    {

        $this->cur = $cur;

        /* Title of the JSON document */
        $this->doc['title'] = null;

        /* Array of activity items */
        $this->doc['items'] = array();

        /* Array of links associated with the document */
        $this->doc['links'] = array();

    }

    /**
     * Set the title of the document
     *
     * @param String $title the title
     */

    function setTitle($title)
    {
        $this->doc['title'] = $title;
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
        $cur = empty($this->cur) ? common_current_user() : $this->cur;

        $act          = $notice->asActivity($cur);
        $act->extra[] = $notice->noticeInfo($cur);
        array_push($this->doc['items'], $act->asArray());
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
        $this->doc['links'][] = $link->asArray();
    }

    /*
     * Return the entire document as a big string of JSON
     *
     * @return string encoded JSON output
     */
    function asString()
    {
        return json_encode(array_filter($this->doc));
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
        $mediaType = null,
        $rel       = null,
        $duration  = null
    )
    {
        parent::__construct($url, $rel, $mediaType);
        $this->linkDict = array(
            'width'      => $width,
            'height'     => $height,
            'duration'   => $duration
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

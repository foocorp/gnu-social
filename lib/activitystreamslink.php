<?php

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

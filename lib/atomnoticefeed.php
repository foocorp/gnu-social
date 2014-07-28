<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for building an Atom feed from a collection of notices
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET'))
{
    exit(1);
}

/**
 * Class for creating a feed that represents a collection of notices. Builds the
 * feed in memory. Get the feed as a string with AtomNoticeFeed::getString().
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AtomNoticeFeed extends Atom10Feed
{
    var $cur;
    protected $scoped=null;

    /**
     * Constructor - adds a bunch of XML namespaces we need in our
     * notice-specific Atom feeds, and allows setting the current
     * authenticated user (useful for API methods).
     *
     * @param User    $cur     the current authenticated user (optional)
     * @param boolean $indent  Whether to indent XML output
     *
     */
    function __construct($cur = null, $indent = true) {
        parent::__construct($indent);

        $this->cur = $cur ?: common_current_user();
        $this->scoped = !is_null($this->cur) ? $this->cur->getProfile() : null;

        // Feeds containing notice info use these namespaces

        $this->addNamespace(
            'thr',
            'http://purl.org/syndication/thread/1.0'
        );

        $this->addNamespace(
            'georss',
            'http://www.georss.org/georss'
        );

        $this->addNamespace(
            'activity',
            'http://activitystrea.ms/spec/1.0/'
        );

        $this->addNamespace(
            'media',
            'http://purl.org/syndication/atommedia'
        );

        $this->addNamespace(
            'poco',
            'http://portablecontacts.net/spec/1.0'
        );

        // XXX: What should the uri be?
        $this->addNamespace(
            'ostatus',
            'http://ostatus.org/schema/1.0'
        );

        $this->addNamespace(
            'statusnet',
            'http://status.net/schema/api/1/'
        );
    }

    /**
     * Add more than one Notice to the feed
     *
     * @param mixed $notices an array of Notice objects or handle
     *
     */
    function addEntryFromNotices($notices)
    {
        if (is_array($notices)) {
            foreach ($notices as $notice) {
                $this->addEntryFromNotice($notice);
            }
        } else {
            while ($notices->fetch()) {
                $this->addEntryFromNotice($notices);
            }
        }
    }

    /**
     * Add a single Notice to the feed
     *
     * @param Notice $notice a Notice to add
     */
    function addEntryFromNotice($notice)
    {
        try {
            $source = $this->showSource();
            $author = $this->showAuthor();

            $this->addEntryRaw($notice->asAtomEntry(false, $source, $author, $this->scoped));
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            // we continue on exceptions
        }
    }

    function showSource()
    {
        return true;
    }

    function showAuthor()
    {
        return true;
    }
}

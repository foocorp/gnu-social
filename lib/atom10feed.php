<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for building an Atom feed in memory
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

class Atom10FeedException extends Exception
{
}

/**
 * Class for building an Atom feed in memory.  Get the finished doc
 * as a string with Atom10Feed::getString().
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Atom10Feed extends XMLStringer
{
    public  $xw;

    // @fixme most of these should probably be read-only properties
    private $namespaces;
    private $authors;
    private $subject;
    private $categories;
    private $contributors;
    private $generator;
    private $icon;
    private $links;
    private $selfLink;
    private $selfLinkType;
    public $logo;
    private $rights;
    public $subtitle;
    public $title;
    private $published;
    private $updated;
    private $entries;

    /**
     * Constructor
     *
     * @param boolean $indent  flag to turn indenting on or off
     *
     * @return void
     */
    function __construct($indent = true) {
        parent::__construct($indent);
        $this->namespaces = array();
        $this->authors    = array();
        $this->links      = array();
        $this->entries    = array();
        $this->addNamespace('', 'http://www.w3.org/2005/Atom');
    }

    /**
     * Add another namespace to the feed
     *
     * @param string $namespace the namespace
     * @param string $uri       namspace uri
     *
     * @return void
     */
    function addNamespace($namespace, $uri)
    {
        $ns = array($namespace => $uri);
        $this->namespaces = array_merge($this->namespaces, $ns);
    }

    function addAuthor($name, $uri = null, $email = null)
    {
        $xs = new XMLStringer(true);

        $xs->elementStart('author');

        if (!empty($name)) {
            $xs->element('name', null, $name);
        } else {
            throw new Atom10FeedException(
                // TRANS: Atom feed exception thrown when an author element does not contain a name element.
                _('Author element must contain a name element.')
            );
        }

        if (isset($uri)) {
            $xs->element('uri', null, $uri);
        }

        if (isset($email)) {
            $xs->element('email', null, $email);
        }

        $xs->elementEnd('author');

        array_push($this->authors, $xs->getString());
    }

    /**
     * Add an Author to the feed via raw XML string
     *
     * @param string $xmlAuthor An XML string representation author
     *
     * @return void
     */
    function addAuthorRaw($xmlAuthor)
    {
        array_push($this->authors, $xmlAuthor);
    }

    function renderAuthors()
    {
        foreach ($this->authors as $author) {
            $this->raw($author);
        }
    }

    function getNamespaces()
    {
        return $this->namespaces;
    }

    function initFeed()
    {
        $this->startXML();
        $commonAttrs = array('xml:lang' => 'en-US');
        foreach ($this->namespaces as $prefix => $uri) {
            if ($prefix == '') {
                $attr = 'xmlns';
            } else {
                $attr = 'xmlns:' . $prefix;
            }
            $commonAttrs[$attr] = $uri;
        }
        $this->elementStart('feed', $commonAttrs);

        $this->element(
            'generator', array(
                'uri'     => 'http://status.net',
                'version' => STATUSNET_VERSION
            ),
            'StatusNet'
        );

        $this->element('id', null, $this->id);
        $this->element('title', null, $this->title);
        $this->element('subtitle', null, $this->subtitle);

        if (!empty($this->logo)) {
            $this->element('logo', null, $this->logo);
        }

        $this->element('updated', null, $this->updated);

        $this->renderAuthors();

        if ($this->selfLink) {
            $this->addLink($this->selfLink, array('rel' => 'self',
                                                  'type' => $this->selfLinkType));
        }
        $this->renderLinks();
    }

    /**
     * Check that all required elements have been set, etc.
     * Throws an Atom10FeedException if something's missing.
     *
     * @return void
     */
    function validate()
    {
    }

    function renderLinks()
    {
        foreach ($this->links as $attrs)
        {
            $this->element('link', $attrs, null);
        }
    }

    function addEntryRaw($xmlEntry)
    {
        array_push($this->entries, $xmlEntry);
    }

    function addEntry($entry)
    {
        array_push($this->entries, $entry->getString());
    }

    function renderEntries()
    {
        foreach ($this->entries as $entry) {
            $this->raw($entry);
        }
    }

    function endFeed()
    {
        $this->elementEnd('feed');
        $this->xw->endDocument();
    }

    function getString()
    {
        if (Event::handle('StartApiAtom', array($this))) {

            $this->validate();
            $this->initFeed();

            if (!empty($this->subject)) {
                $this->raw($this->subject);
            }

            $this->renderEntries();
            $this->endFeed();

            Event::handle('EndApiAtom', array($this));
        }

        return $this->xw->outputMemory();
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setSelfLink($url, $type='application/atom+xml')
    {
        $this->selfLink = $url;
        $this->selfLinkType = $type;
    }

    function setTitle($title)
    {
        $this->title = $title;
    }

    function setSubtitle($subtitle)
    {
        $this->subtitle = $subtitle;
    }

    function setLogo($logo)
    {
        $this->logo = $logo;
    }

    function setUpdated($dt)
    {
        $this->updated = common_date_iso8601($dt);
    }

    function setPublished($dt)
    {
        $this->published = common_date_iso8601($dt);
    }

    /**
     * Adds a link element into the Atom document
     *
     * Assumes you want rel="alternate" and type="text/html" unless
     * you send in $otherAttrs.
     *
     * @param string $uri            the uri the href needs to point to
     * @param array  $otherAttrs     other attributes to stick in
     *
     * @return void
     */
    function addLink($uri, $otherAttrs = null) {
        $attrs = array('href' => $uri);

        if (is_null($otherAttrs)) {
            $attrs['rel']  = 'alternate';
            $attrs['type'] = 'text/html';
        } else {
            $attrs = array_merge($attrs, $otherAttrs);
        }

        array_push($this->links, $attrs);
    }
}

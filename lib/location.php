<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for locations
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
 * @category  Location
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
 * class for locations
 *
 * These are stored in the DB as part of notice and profile records,
 * but since they're about the same in both, we have a separate class
 * for them.
 *
 * @category Location
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class Location
{
    public  $lat;
    public  $lon;
    public  $location_id;
    public  $location_ns;
    private $_url;
    private $_rdfurl;

    var $names = array();

    /**
     * Constructor that makes a Location from Notice::locationOptions(...)
     *
     * @param array $options    an array for example provided by Notice::locationOptions(...)
     *
     * @return Location Location with the given options (lat, lon, id, name)
     */
    static function fromOptions(array $options) {
        $location = new Location();
        foreach (['lat', 'lon', 'location_id', 'location_ns'] as $opt) {
            if (isset($options[$opt])) {
                $location->$opt = $options[$opt];
            }
        }
        return $location;
    }

    /**
     * Constructor that makes a Location from a string name
     *
     * @param string $name     Human-readable name (any kind)
     * @param string $language Language, default = common_language()
     *
     * @return Location Location with that name (or null if not found)
     */

    static function fromName($name, $language=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }

        $location = null;

        // Let a third-party handle it

        Event::handle('LocationFromName', array($name, $language, &$location));

        return $location;
    }

    /**
     * Constructor that makes a Location from an ID
     *
     * @param integer $id       Identifier ID
     * @param integer $ns       Namespace of the identifier
     * @param string  $language Language to return name in (default is common)
     *
     * @return Location The location with this ID (or null if none)
     */

    static function fromId($id, $ns, $language=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }

        $location = null;

        // Let a third-party handle it

        Event::handle('LocationFromId', array($id, $ns, $language, &$location));

        return $location;
    }

    /**
     * Constructor that finds the nearest location to a lat/lon pair
     *
     * @param float  $lat      Latitude
     * @param float  $lon      Longitude
     * @param string $language Language for results, default = current
     *
     * @return Location the location found, or null if none found
     */

    static function fromLatLon($lat, $lon, $language=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }

        $location = null;

        // Let a third-party handle it

        if (Event::handle('LocationFromLatLon',
                          array($lat, $lon, $language, &$location))) {
            // Default is just the lat/lon pair

            $location = new Location();

            $location->lat = $lat;
            $location->lon = $lon;
        }

        return $location;
    }

    /**
     * Get the name for this location in the given language
     *
     * @param string $language language to use, default = current
     *
     * @return string location name or null if not found
     */

    function getName($language=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }

        if (array_key_exists($language, $this->names)) {
            return $this->names[$language];
        } else {
            $name = null;
            Event::handle('LocationNameLanguage', array($this, $language, &$name));
            if (!empty($name)) {
                $this->names[$language] = $name;
                return $name;
            }
        }
    }

    /**
     * Get an URL suitable for this location
     *
     * @return string URL for this location or NULL
     */

    function getURL()
    {
        // Keep one cached

        if (is_string($this->_url)) {
            return $this->_url;
        }

        $url = null;

        Event::handle('LocationUrl', array($this, &$url));

        $this->_url = $url;

        return $url;
    }

    /**
     * Get an URL for this location, suitable for embedding in RDF
     *
     * @return string URL for this location or NULL
     */

    function getRdfURL()
    {
        // Keep one cached

        if (is_string($this->_rdfurl)) {
            return $this->_rdfurl;
        }

        $url = null;

        Event::handle('LocationRdfUrl', array($this, &$url));

        $this->_rdfurl = $url;

        return $url;
    }
}

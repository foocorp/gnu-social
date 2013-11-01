<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to convert string locations to Geonames IDs and vice versa
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to convert string locations to Geonames IDs and vice versa
 *
 * This handles most of the events that Location class emits. It uses
 * the geonames.org Web service to convert names like 'Montreal, Quebec, Canada'
 * into IDs and lat/lon pairs.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */
class GeonamesPlugin extends Plugin
{
    const LOCATION_NS = 1;

    public $host     = 'ws.geonames.org';
    public $username = null;
    public $token    = null;
    public $expiry   = 7776000; // 90-day expiry
    public $timeout  = 2;       // Web service timeout in seconds.
    public $timeoutWindow = 60; // Further lookups in this process will be disabled for N seconds after a timeout.
    public $cachePrefix = null; // Optional shared memcache prefix override
                                // to share lookups between local instances.

    protected $lastTimeout = null; // timestamp of last web service timeout

    /**
     * convert a name into a Location object
     *
     * @param string   $name      Name to convert
     * @param string   $language  ISO code for anguage the name is in
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */
    function onLocationFromName($name, $language, &$location)
    {
        $loc = $this->getCache(array('name' => $name,
                                     'language' => $language));

        if ($loc !== false) {
            $location = $loc;
            return false;
        }

        try {
            $geonames = $this->getGeonames('search',
                                           array('maxRows' => 1,
                                                 'q' => $name,
                                                 'lang' => $language,
                                                 'type' => 'xml'));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for $name: " . $e->getMessage());
            return true;
        }

        if (count($geonames) == 0) {
            // no results
            $this->setCache(array('name' => $name,
                                  'language' => $language),
                            null);
            return true;
        }

        $n = $geonames[0];

        $location = new Location();

        $location->lat              = $this->canonical($n->lat);
        $location->lon              = $this->canonical($n->lng);
        $location->names[$language] = (string)$n->name;
        $location->location_id      = (string)$n->geonameId;
        $location->location_ns      = self::LOCATION_NS;

        $this->setCache(array('name' => $name,
                              'language' => $language),
                        $location);

        // handled, don't continue processing!
        return false;
    }

    /**
     * convert an id into a Location object
     *
     * @param string   $id        Name to convert
     * @param string   $ns        Name to convert
     * @param string   $language  ISO code for language for results
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */
    function onLocationFromId($id, $ns, $language, &$location)
    {
        if ($ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $loc = $this->getCache(array('id' => $id));

        if ($loc !== false) {
            $location = $loc;
            return false;
        }

        try {
            $geonames = $this->getGeonames('hierarchy',
                                           array('geonameId' => $id,
                                                 'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for ID $id: " . $e->getMessage());
            return false;
        }

        $parts = array();

        foreach ($geonames as $level) {
            if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                $parts[] = (string)$level->name;
            }
        }

        $last = $geonames[count($geonames)-1];

        if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
            $parts[] = (string)$last->name;
        }

        $location = new Location();

        $location->location_id      = (string)$last->geonameId;
        $location->location_ns      = self::LOCATION_NS;
        $location->lat              = $this->canonical($last->lat);
        $location->lon              = $this->canonical($last->lng);

        $location->names[$language] = implode(', ', array_reverse($parts));

        $this->setCache(array('id' => (string)$last->geonameId),
                        $location);

        // We're responsible for this namespace; nobody else
        // can resolve it

        return false;
    }

    /**
     * convert a lat/lon pair into a Location object
     *
     * Given a lat/lon, we try to find a Location that's around
     * it or nearby. We prefer populated places (cities, towns, villages).
     *
     * @param string   $lat       Latitude
     * @param string   $lon       Longitude
     * @param string   $language  ISO code for language for results
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */
    function onLocationFromLatLon($lat, $lon, $language, &$location)
    {
        // Make sure they're canonical

        $lat = $this->canonical($lat);
        $lon = $this->canonical($lon);

        $loc = $this->getCache(array('lat' => $lat,
                                     'lon' => $lon));

        if ($loc !== false) {
            $location = $loc;
            return false;
        }

        try {
          $geonames = $this->getGeonames('findNearbyPlaceName',
                                         array('lat' => $lat,
                                               'lng' => $lon,
                                               'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for coords $lat, $lon: " . $e->getMessage());
            return true;
        }

        if (count($geonames) == 0) {
            // no results
            $this->setCache(array('lat' => $lat,
                                  'lon' => $lon),
                            null);
            return true;
        }

        $n = $geonames[0];

        $parts = array();

        $location = new Location();

        $parts[] = (string)$n->name;

        if (!empty($n->adminName1)) {
            $parts[] = (string)$n->adminName1;
        }

        if (!empty($n->countryName)) {
            $parts[] = (string)$n->countryName;
        }

        $location->location_id = (string)$n->geonameId;
        $location->location_ns = self::LOCATION_NS;
        $location->lat         = $this->canonical($n->lat);
        $location->lon         = $this->canonical($n->lng);

        $location->names[$language] = implode(', ', $parts);

        $this->setCache(array('lat' => $lat,
                              'lon' => $lon),
                        $location);

        // Success! We handled it, so no further processing

        return false;
    }

    /**
     * Human-readable name for a location
     *
     * Given a location, we try to retrieve a human-readable name
     * in the target language.
     *
     * @param Location $location Location to get the name for
     * @param string   $language ISO code for language to find name in
     * @param string   &$name    Place to put the name
     *
     * @return boolean whether to continue
     */
    function onLocationNameLanguage($location, $language, &$name)
    {
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $id = $location->location_id;

        $n = $this->getCache(array('id' => $id,
                                   'language' => $language));

        if ($n !== false) {
            $name = $n;
            return false;
        }

        try {
            $geonames = $this->getGeonames('hierarchy',
                                           array('geonameId' => $id,
                                                 'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for ID $id: " . $e->getMessage());
            return false;
        }

        if (count($geonames) == 0) {
            $this->setCache(array('id' => $id,
                                  'language' => $language),
                            null);
            return false;
        }

        $parts = array();

        foreach ($geonames as $level) {
            if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                $parts[] = (string)$level->name;
            }
        }

        $last = $geonames[count($geonames)-1];

        if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
            $parts[] = (string)$last->name;
        }

        if (count($parts)) {
            $name = implode(', ', array_reverse($parts));
            $this->setCache(array('id' => $id,
                                  'language' => $language),
                            $name);
        }

        return false;
    }

    /**
     * Human-readable URL for a location
     *
     * Given a location, we try to retrieve a geonames.org URL.
     *
     * @param Location $location Location to get the url for
     * @param string   &$url     Place to put the url
     *
     * @return boolean whether to continue
     */
    function onLocationUrl($location, &$url)
    {
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $url = 'http://www.geonames.org/' . $location->location_id;

        // it's been filled, so don't process further.
        return false;
    }

    /**
     * Machine-readable name for a location
     *
     * Given a location, we try to retrieve a geonames.org URL.
     *
     * @param Location $location Location to get the url for
     * @param string   &$url     Place to put the url
     *
     * @return boolean whether to continue
     */
    function onLocationRdfUrl($location, &$url)
    {
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $url = 'http://sws.geonames.org/' . $location->location_id . '/';

        // it's been filled, so don't process further.
        return false;
    }

    function getCache($attrs)
    {
        $c = Cache::instance();

        if (empty($c)) {
            return null;
        }

        $key = $this->cacheKey($attrs);

        $value = $c->get($key);

        return $value;
    }

    function setCache($attrs, $loc)
    {
        $c = Cache::instance();

        if (empty($c)) {
            return null;
        }

        $key = $this->cacheKey($attrs);

        $result = $c->set($key, $loc, 0, time() + $this->expiry);

        return $result;
    }

    function cacheKey($attrs)
    {
        $key = 'geonames:' .
               implode(',', array_keys($attrs)) . ':'.
               Cache::keyize(implode(',', array_values($attrs)));
        if ($this->cachePrefix) {
            return $this->cachePrefix . ':' . $key;
        } else {
            return Cache::key($key);
        }
    }

    function wsUrl($method, $params)
    {
        if (!empty($this->username)) {
            $params['username'] = $this->username;
        }

        if (!empty($this->token)) {
            $params['token'] = $this->token;
        }

        $str = http_build_query($params, null, '&');

        return 'http://'.$this->host.'/'.$method.'?'.$str;
    }

    function getGeonames($method, $params)
    {
        if ($this->lastTimeout && (time() - $this->lastTimeout < $this->timeoutWindow)) {
            // TRANS: Exception thrown when a geo names service is not used because of a recent timeout.
            throw new Exception(_m('Skipping due to recent web service timeout.'));
        }

        $client = HTTPClient::start();
        $client->setConfig('connect_timeout', $this->timeout);
        $client->setConfig('timeout', $this->timeout);

        try {
            $result = $client->get($this->wsUrl($method, $params));
        } catch (Exception $e) {
            common_log(LOG_ERR, __METHOD__ . ": " . $e->getMessage());
            $this->lastTimeout = time();
            throw $e;
        }

        if (!$result->isOk()) {
            // TRANS: Exception thrown when a geo names service does not return an expected response.
            // TRANS: %s is an HTTP error code.
            throw new Exception(sprintf(_m('HTTP error code %s.'),$result->getStatus()));
        }

        $body = $result->getBody();

        if (empty($body)) {
            // TRANS: Exception thrown when a geo names service returns an empty body.
            throw new Exception(_m('Empty HTTP body in response.'));
        }

        // This will throw an exception if the XML is mal-formed

        $document = new SimpleXMLElement($body);

        // No children, usually no results

        $children = $document->children();

        if (count($children) == 0) {
            return array();
        }

        if (isset($document->status)) {
            // TRANS: Exception thrown when a geo names service return a specific error number and error text.
            // TRANS: %1$s is an error code, %2$s is an error message.
            throw new Exception(sprintf(_m('Error #%1$s ("%2$s").'),$document->status['value'],$document->status['message']));
        }

        // Array of elements, >0 elements

        return $document->geoname;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Geonames',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Geonames',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Uses <a href="http://geonames.org/">Geonames</a> service to get human-readable '.
                               'names for locations based on user-provided lat/long pairs.'));
        return true;
    }

    function canonical($coord)
    {
        $coord = rtrim($coord, "0");
        $coord = rtrim($coord, ".");

        return $coord;
    }
}

<?php
/**
 * Geocode action class
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Geocode action class
 *
 * @category Action
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class GeocodeAction extends Action
{
    var $lat = null;
    var $lon = null;
    var $location = null;

    function prepare($args)
    {
        parent::prepare($args);
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. '.
                                 'Try again, please.'));
        }
        $this->lat = $this->trimmed('lat');
        $this->lon = $this->trimmed('lon');
        $this->location = Location::fromLatLon($this->lat, $this->lon);
        return true;
    }

    /**
     * Class handler
     *
     * @param array $args query arguments
     *
     * @return nothing
     *
     */
    function handle($args)
    {
        header('Content-Type: application/json; charset=utf-8');
        $location_object = array();
        $location_object['lat']=$this->lat;
        $location_object['lon']=$this->lon;
        if($this->location) {
            $location_object['location_id']=$this->location->location_id;
            $location_object['location_ns']=$this->location->location_ns;
            $location_object['name']=$this->location->getName();
            $location_object['url']=$this->location->getUrl();
        }
        print(json_encode($location_object));
    }

    /**
     * Is this action read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }
}

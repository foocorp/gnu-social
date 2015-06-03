<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Low-level generator for HTML
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
 * @category  Output
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2008 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Low-level generator for XRDS XML
 *
 * @category Output
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Action
 * @see      XMLOutputter
 */
class XRDSOutputter extends XMLOutputter
{
    public function startXRDS()
    {
        header('Content-Type: application/xrds+xml');
        $this->startXML();
        $this->elementStart('XRDS', array('xmlns' => 'xri://$xrds'));
    }
    
    public function endXRDS()
    {
        $this->elementEnd('XRDS');
        $this->endXML();
    }

    /**
     * Show service.
     *
     * @param string $type    XRDS type
     * @param string $uri     URI
     * @param array  $params  type parameters, null by default
     * @param array  $sigs    type signatures, null by default
     * @param string $localId local ID, null by default
     *
     * @return void
     */
    function showXrdsService($type, $uri, $params=null, $sigs=null, $localId=null)
    {
        $this->elementStart('Service');
        if ($uri) {
            $this->element('URI', null, $uri);
        }
        $this->element('Type', null, $type);
        if ($params) {
            foreach ($params as $param) {
                $this->element('Type', null, $param);
            }
        }
        if ($sigs) {
            foreach ($sigs as $sig) {
                $this->element('Type', null, $sig);
            }
        }
        if ($localId) {
            $this->element('LocalID', null, $localId);
        }
        $this->elementEnd('Service');
    }
}

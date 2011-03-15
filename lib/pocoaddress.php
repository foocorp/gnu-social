<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PoCoAddress
{
    const ADDRESS   = 'address';
    const FORMATTED = 'formatted';

    public $formatted;

    // @todo Other address fields

    function asString()
    {
        $xs = new XMLStringer(true);
        $this->outputTo($xs);
        return $xs->getString();
    }

    function outputTo($xo)
    {
        if (!empty($this->formatted)) {
            $xo->elementStart('poco:address');
            $xo->element('poco:formatted', null, common_xml_safe_str($this->formatted));
            $xo->elementEnd('poco:address');
        }
    }

    /**
     * Return this PoCo address as an array suitable for serializing in JSON
     *
     * @return array the address
     */

    function asArray()
    {
        if (!empty($this->formatted)) {
            return array('formatted' => $this->formatted);
        }
    }
}

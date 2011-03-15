<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for deleting things
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
 * @category  Personal
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
     exit(1);
}

class WebColor {
    // XXX: Maybe make getters and setters for r,g,b values and tuples,
    // e.g.: to support this kinda CSS representation: rgb(255,0,0)
    // http://www.w3.org/TR/CSS21/syndata.html#color-units

    var $red   = 0;
    var $green = 0;
    var $blue  = 0;

    /**
     * Constructor
     *
     * @return nothing
     */
    function __construct($color = null)
    {
        if (isset($color)) {
            $this->parseColor($color);
        }
    }

    /**
     * Parses input to and tries to determine whether the color
     * is being specified via an integer or hex tuple and sets
     * the RGB instance variables accordingly.
     *
     * XXX: Maybe support (r,g,b) style, and array?
     *
     * @param mixed $color
     *
     * @return nothing
     */
    function parseColor($color) {

        if (is_numeric($color)) {
            $this->setIntColor($color);
        } else {

            // XXX named colors

            // XXX: probably should do even more validation

            if (preg_match('/(#([0-9A-Fa-f]{3,6})\b)/u', $color) > 0) {
                $this->setHexColor($color);
            } else {
                // TRANS: Web color exception thrown when a hexadecimal color code does not validate.
                // TRANS: %s is the provided (invalid) color code.
                $errmsg = _('%s is not a valid color! Use 3 or 6 hex characters.');
                throw new WebColorException(sprintf($errmsg, $color));
            }
        }
    }

    /**
     * @param string $name
     *
     * @return nothing
     */
    function setNamedColor($name)
    {
        // XXX Implement this
    }

    /**
     * Sets the RGB color values from a a hex tuple
     *
     * @param string $hexcolor
     *
     * @return nothing
     */
    function setHexColor($hexcolor) {

        if ($hexcolor[0] == '#') {
            $hexcolor = substr($hexcolor, 1);
        }

        if (strlen($hexcolor) == 6) {
            list($r, $g, $b) = array($hexcolor[0].$hexcolor[1],
                                     $hexcolor[2].$hexcolor[3],
                                     $hexcolor[4].$hexcolor[5]);
        } elseif (strlen($hexcolor) == 3) {
            list($r, $g, $b) = array($hexcolor[0].$hexcolor[0],
                                     $hexcolor[1].$hexcolor[1],
                                     $hexcolor[2].$hexcolor[2]);
        } else {
            // TRANS: Web color exception thrown when a hexadecimal color code does not validate.
            // TRANS: %s is the provided (invalid) color code.
            $errmsg = _('%s is not a valid color! Use 3 or 6 hex characters.');
            throw new WebColorException(sprintf($errmsg, $hexcolor));
        }

        $this->red   = hexdec($r);
        $this->green = hexdec($g);
        $this->blue  = hexdec($b);

    }

    /**
     * Sets the RGB color values from a 24-bit integer
     *
     * @param int $intcolor
     *
     * @return nothing
     */
    function setIntColor($intcolor)
    {
        // We could do 32 bit and have an alpha channel because
        // Sarven wants one real bad, but nah.

        $this->red   = $intcolor >> 16;
        $this->green = $intcolor >> 8 & 0xFF;
        $this->blue  = $intcolor & 0xFF;

    }

    /**
     * Returns a hex tuple of the RGB color useful for output in HTML
     *
     * @return string
     */
    function hexValue() {

        $hexcolor  = (strlen(dechex($this->red)) < 2 ? '0' : '' ) .
            dechex($this->red);
        $hexcolor .= (strlen(dechex($this->green)) < 2 ? '0' : '') .
            dechex($this->green);
        $hexcolor .= (strlen(dechex($this->blue)) < 2 ? '0' : '') .
            dechex($this->blue);

        return strtoupper($hexcolor);
    }

    /**
     * Returns a 24-bit packed integer representation of the RGB color
     * for convenient storage in the DB
     *
     * XXX: probably could just use hexdec() instead
     *
     * @return int
     */
    function intValue()
    {
        $intcolor = 256 * 256 * $this->red + 256 * $this->green + $this->blue;
        return $intcolor;
    }

}

class WebColorException extends Exception
{
}

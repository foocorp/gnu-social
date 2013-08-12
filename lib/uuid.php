<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * UUID generation
 * 
 * PHP version 5
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
 *
 * @category  UUID
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * UUID generation
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class UUID
{
    const REGEX = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    protected $str = null;

    /**
     * Constructor for a UUID
     * 
     * Uses gen() to create a new UUID
     */

    function __construct()
    {
        $this->str = self::gen();
    }

    /**
     * For serializing to a string
     *
     * @return string version of self
     */

    function __toString()
    {
        return $this->str;
    }

    /**
     * For serializing to a string
     *
     * @return string version of self
     */

    function getString()
    {
        return $this->str;
    }

    /**
     * Generate a new UUID
     *
     * @return 36-char v4 (random-ish) UUID
     */

    static function gen()
    {
        return sprintf('%s-%s-%04x-%04x-%s',
                       // 32 bits for "time_low"
                       common_good_rand(4),
                       // 16 bits for "time_mid"
                       common_good_rand(2),
                       // 16 bits for "time_hi_and_version",
                       // four most significant bits holds version number 4
                       (hexdec(common_good_rand(2)) & 0x0fff) | 0x4000,
                       // 16 bits, 8 bits for "clk_seq_hi_res",
                       // 8 bits for "clk_seq_low",
                       // two most significant bits holds zero and one
                       // for variant DCE1.1
                       (hexdec(common_good_rand(2)) & 0x3fff) | 0x8000,
                       // 48 bits for "node"
                       common_good_rand(6));
    }   
}

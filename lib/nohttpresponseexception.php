<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for an exception when an HTTP request gets no response (DNS failure etc.)
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
 * @category  Exception
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

// Can't extend HTTP_Request2_Exception since it requires an HTTP status code which we didn't get
class NoHttpResponseException extends Exception
{
    public $url;    // target URL

    public function __construct($url)
    {
        $this->url = $url;
        // We could log an entry here with the search parameters
        parent::__construct(sprintf(_('No HTTP response from URL %s.'), _ve($url)));
    }
}

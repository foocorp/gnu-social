<?php
/**
 * GNU social - a federating social network
 *
 * class for when an object that's supposed to have a URI is missing it
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
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      https://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class NoUriException extends ServerException
{
    var $object = null;

    public function __construct(Managed_DataObject $object)
    {
        $this->object = $object;
        $msg = get_class($object) . ' does not have a URI.';
        parent::__construct($msg);
    }
}

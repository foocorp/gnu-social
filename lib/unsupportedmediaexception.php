<?php
/**
 * GNU social - a federating social network
 *
 * class for an exception when a File is of unsupported media type
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

class UnsupportedMediaException extends ServerException
{
    public function __construct($msg, $path)
    {
        common_debug(sprintf('UnsupportedMediaException "%1$s" for file "%2$s"', $msg, $path));
        parent::__construct($msg);
    }
}

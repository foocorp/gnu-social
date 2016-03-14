<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for an exception when a filename is invalid
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
 * @package   StatusNet
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2016 Free Software Foundation, Inc.
 * @license   https://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Class for an exception when a filename is invalid
 *
 * @category Exception
 * @package  GNUsocial
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  https://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     https://gnu.io/social
 */

class InvalidFilenameException extends ServerException
{
    public $filename = null;

    public function __construct($filename)
    {
        $this->filename = $filename;
        // We could log an entry here with the search parameters
        parent::__construct(_('Invalid filename.'));
    }
}

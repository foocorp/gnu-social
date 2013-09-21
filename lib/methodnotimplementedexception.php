<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * class for an exception when a method is not implemented
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
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) {
    exit(1);
}

/**
 * Class for an exception when a local user is not found by certain criteria
 *
 * @category Exception
 * @package  StatusNet
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class MethodNotImplementedException extends ServerException
{
    public function __construct($method)
    {
        parent::__construct(sprintf(_('Method %s not implemented'), $method));
    }
}

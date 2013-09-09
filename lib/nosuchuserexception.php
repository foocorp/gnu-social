<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * class for an exception when a local user is not found by certain criteria
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class for an exception when a local user is not found by certain criteria
 *
 * @category Exception
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class NoSuchUserException extends ServerException
{
    public $data = array();

    /**
     * constructor
     *
     * @param array $data user search criteria
     */

    public function __construct(array $data)
    {
        // filter on unique keys for local users
        foreach(array('id', 'email', 'nickname') as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $this->data[$key] = $data[$key];
            }
        }

        // Here we could log the failed lookup

        parent::__construct(_('No such user found.'));
    }
}

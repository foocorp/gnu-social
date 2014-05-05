<?php
/**
 * GNU social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Parent class for an exception when trying to do something that was
 * probably already done.
 *
 * This is a common case for example when remote sites are not up to
 * date with our database. For example subscriptions, where a remote
 * user may be unsubscribed from our user, but they request it anyway. 
 *
 * This exception is usually caught in a manner that lets the execution
 * continue _as if_ the desired action did what it was supposed to do.
 *
 * @category Exception
 * @package  GNUsocial
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://gnu.io/
 */

class AlreadyFulfilledException extends ServerException
{
    public function __construct($msg=null)
    {
        if ($msg === null) {
            // TRANS: Exception text when attempting to perform something which seems already done.
            $msg = _('Trying to do something that was already done.');
        }

        parent::__construct($msg, 409);
    }
}

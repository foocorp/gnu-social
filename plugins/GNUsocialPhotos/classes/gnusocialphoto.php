<?php
/**
 * GNU Social
 * Copyright (C) 2010, Free Software Foundation, Inc.
 *
 * PHP version 5
 *
 * LICENCE:
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
 * @category  Widget
 * @package   GNU Social
 * @author    Ian Denhardt <ian@zenhack.net>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class GNUsocialPhoto extends Memcached_DataObject
{
    public $object_id;   // integer
    public $path;        // varchar(150)
    public $thumb_path;  // varchar(156)
    public $owner_id;    // int(11) (user who posted the photo)

    function staticGet($k,$v=NULL)
    {
        return Memcached_DataObject::staticGet('GNUsocialPhoto',$k,$v);
    }

    function delete()
    {
        if(!unlink(INSTALLDIR . $this->thumb_path)) {
            return false;
        }
        if(!unlink(INSTALLDIR . $this->path)) {
            return false;
        }
        return parent::delete();
    }
}

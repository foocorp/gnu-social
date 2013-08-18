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
 * @author    Sean Corbett <sean@gnu.org>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class GNUsocialPhotoAlbum extends Managed_DataObject
{
    public $__table = 'GNUsocialPhotoAlbum';
    public $album_id;          // int(11) -- Unique identifier for the album
    public $profile_id;        // int(11) -- Profile ID for the owner of the album
    public $album_name;        // varchar(256) -- Title for this album
    public $album_description; // text -- A description of the album
    
    /* TODO: Primary key on both album_id, profile_id / foriegn key on profile_id */
    function table()
    {
        return array('album_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'album_name' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'album_description' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL);
    }
    
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    
    /* Using album_id as the primary key for now.. */
    function keyTypes()
    {
        return array('album_id' => 'K');
    }

    function sequenceKey()
    {
        return array('album_id', true, false);
    }

    function getPageLink()
    {
        $profile = Profile::StaticGet('id', $this->profile_id);
        return '/' . $profile->nickname . '/photos/' . $this->album_id;
    }

    function getThumbUri()
    {
        $photo = GNUsocialPhoto::staticGet('album_id', $this->album_id);
        if (empty($photo))
            return '/theme/default/default-avatar-profile.png'; //For now...
        return $photo->thumb_uri;
    }

    static function newAlbum($profile_id, $album_name, $album_description)
    {
        //TODO: Should use foreign key instead...
        if (!Profile::staticGet('id', $profile_id)){
            //Is this a bit extreme?
            throw new ServerException(_m('No such user exists with id ' . $profile_id . ', couldn\'t create album.'));
        }
        
        $album = new GNUsocialPhotoAlbum();
        $album->profile_id = $profile_id;
        $album->album_name = $album_name;
        $album->album_description = $album_description;
       
        $album->album_id = $album->insert();
        if (!$album->album_id){
            common_log_db_error($album, 'INSERT', __FILE__);
            throw new ServerException(_m('Error creating new album.'));
        }
        common_log(LOG_INFO, 'album_id : ' . $album->album_id);
        return $album;
    }

}

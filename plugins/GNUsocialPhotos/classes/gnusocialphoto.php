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

class GNUsocialPhoto extends Managed_DataObject
{
    public $__table = 'GNUsocialPhoto';
    public $id;         // int(11)
    public $notice_id;  // int(11)
    public $album_id;   // int(11)
    public $uri;        // varchar(255)
    public $thumb_uri;  // varchar(255)
	public $title;      // varchar(255)
	public $photo_description; // text
    public $created;           // datetime()   not_null
    public $modified;          // timestamp()   not_null default_CURRENT_TIMESTAMP

/*    function delete()
    {
        if(!unlink(INSTALLDIR . $this->thumb_uri)) {
            return false;
        }
        if(!unlink(INSTALLDIR . $this->path)) {
            return false;
        }
        return parent::delete();
    } */

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'Unique ID for Photo'),
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'Notice ID for the related notice'),
                'album_id' => array('type' => 'int', 'not null' => true, 'description' => 'The parent album ID'),
                'uri' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'unique address for this photo'),
                'thumb_uri' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'unique address for this photo thumbnail'),
                'title' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'The Photo title'),
                'photo_description' => array('type' => 'text', 'not null' => true, 'description' => 'A description for this photo'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'gnusocialphoto_id_key' => array('notice_id'),
                'gnusocialphoto_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'gnusocialphoto_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
                'gnusocialphoto_album_id_fkey' => array('GNUsocialPhotoAlbum', array('album_id' => 'id')),
            ),
            'indexes' => array(
                'gnusocialphoto_title_idx' => array('title'),
            ),
        );
    }

    static function saveNew($profile_id, $album_id, $thumb_uri, $uri, $source, $insert_now, $title = null, $photo_description = null)
    {
        $photo = new GNUsocialPhoto();
        $photo->thumb_uri = $thumb_uri;
        $photo->uri = $uri;
		$photo->album_id = $album_id;
		if(!empty($title)) $photo->title = $title;
		if(!empty($photo_description)) $photo->photo_description = (string)$photo_description;

        if($insert_now) {
            $notice = Notice::saveNew($profile_id, $uri, $source);
            $photo->notice_id = $notice->id;
            $photo_id = $photo->insert();
            if (!$photo_id) {
                common_log_db_error($photo, 'INSERT', __FILE__);
                throw new ServerException(_m('Problem Saving Photo.'));
            }
        } else {
            GNUsocialPhotoTemp::$tmp = $photo;
            Notice::saveNew($profile_id, $uri, $source);
        }
    }

    function getPageLink()
    {
        return '/photo/' . $this->id;
    }

    /*
     * TODO: -Sanitize input
     * @param int page_id The desired page of the gallery to show.
     * @param int album_id The id of the album to get photos from.
     * @param int gallery_size The number of thumbnails to show per page in the gallery.
     * @return array Array of GNUsocialPhotos for this gallery page.
     */
    static function getGalleryPage($page_id, $album_id, $gallery_size)
    {
		$page_offset = ($page_id-1) * $gallery_size; 
        $sql = 'SELECT * FROM GNUsocialPhoto WHERE album_id = ' . $album_id . 
               ' ORDER BY notice_id LIMIT ' . $page_offset . ',' . $gallery_size;
        $photo = new GNUsocialPhoto();
        $photo->query($sql);
        $photos = array();

        while ($photo->fetch()) {
            $photos[] = clone($photo);
        }

        return $photos;
    }
}

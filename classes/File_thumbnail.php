<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for file_thumbnail
 */

class File_thumbnail extends Managed_DataObject
{
    public $__table = 'file_thumbnail';                  // table name
    public $file_id;                         // int(4)  primary_key not_null
    public $url;                             // varchar(255)  unique_key
    public $filename;                        // varchar(255)
    public $width;                           // int(4)  primary_key
    public $height;                          // int(4)  primary_key
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'thumbnail for what URL/file'),
                'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of thumbnail'),
                'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'if stored locally, filename is put here'),
                'width' => array('type' => 'int', 'description' => 'width of thumbnail'),
                'height' => array('type' => 'int', 'description' => 'height of thumbnail'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('file_id', 'width', 'height'),
            'indexes' => array(
                'file_thumbnail_file_id_idx' => array('file_id'),
            ),
            'foreign keys' => array(
                'file_thumbnail_file_id_fkey' => array('file', array('file_id' => 'id')),
            )
        );
    }

    /**
     * Save oEmbed-provided thumbnail data
     *
     * @param object $data
     * @param int $file_id
     */
    public static function saveNew($data, $file_id) {
        if (!empty($data->thumbnail_url)) {
            // Non-photo types such as video will usually
            // show us a thumbnail, though it's not required.
            self::saveThumbnail($file_id,
                                $data->thumbnail_url,
                                $data->thumbnail_width,
                                $data->thumbnail_height);
        } else if ($data->type == 'photo') {
            // The inline photo URL given should also fit within
            // our requested thumbnail size, per oEmbed spec.
            self::saveThumbnail($file_id,
                                $data->url,
                                $data->width,
                                $data->height);
        }
    }

    /**
     * Fetch an entry by using a File's id
     */
    static function byFile(File $file) {
        $file_thumbnail = self::getKV('file_id', $file->id);
        if (!$file_thumbnail instanceof File_thumbnail) {
            throw new ServerException(sprintf('No File_thumbnail entry for File id==%u', $file->id));
        }
        return $file_thumbnail;
    }

    /**
     * Save a thumbnail record for the referenced file record.
     *
     * FIXME: Add error handling
     *
     * @param int $file_id
     * @param string $url
     * @param int $width
     * @param int $height
     */
    static function saveThumbnail($file_id, $url, $width, $height, $filename=null)
    {
        $tn = new File_thumbnail;
        $tn->file_id = $file_id;
        $tn->url = $url;
        $tn->filename = $filename;
        $tn->width = intval($width);
        $tn->height = intval($height);
        $tn->insert();
        return $tn;
    }

    static function path($filename)
    {
        // TODO: Store thumbnails in their own directory and don't use File::path here
        return File::path($filename);
    }

    public function getPath()
    {
        return self::path($this->filename);
    }

    public function getUrl()
    {
        if (!empty($this->getFile()->filename)) {
            // A locally stored File, so let's generate a URL for our instance.
            $url = File::url($this->filename);
            if ($url != $this->url) {
                // For indexing purposes, in case we do a lookup on the 'url' field.
                // also we're fixing possible changes from http to https, or paths
                $this->updateUrl($url);
            }
            return $url;
        }

        // No local filename available, return the URL we have stored
        return $this->url;
    }

    public function updateUrl($url)
    {
        $file = File_thumbnail::getKV('url', $url);
        if ($file instanceof File_thumbnail) {
            throw new ServerException('URL already exists in DB');
        }
        $sql = 'UPDATE %1$s SET url=%2$s WHERE url=%3$s;';
        $result = $this->query(sprintf($sql, $this->__table,
                                             $this->_quote((string)$url),
                                             $this->_quote((string)$this->url)));
        if ($result === false) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            throw new ServerException("Could not UPDATE {$this->__table}.url");
        }

        return $result;
    }

    public function delete($useWhere=false)
    {
        if (!empty($this->filename) && file_exists(File_thumbnail::path($this->filename))) {
            $deleted = @unlink(self::path($this->filename));
            if (!$deleted) {
                common_log(LOG_ERR, sprintf('Could not unlink existing file: "%s"', self::path($this->filename)));
            }
        }

        return parent::delete($useWhere);
    }

    public function getFile()
    {
        $file = new File();
        $file->id = $this->file_id;
        if (!$file->find(true)) {
            throw new NoResultException($file);
        }
        return $file;
    }
}

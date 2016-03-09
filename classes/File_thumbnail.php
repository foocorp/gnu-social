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
    public $urlhash;                         // varchar(64) indexed
    public $url;                             // text
    public $filename;                        // text
    public $width;                           // int(4)  primary_key
    public $height;                          // int(4)  primary_key
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    const URLHASH_ALG = 'sha256';

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'thumbnail for what URL/file'),
                'urlhash' => array('type' => 'varchar', 'length' => 64, 'description' => 'sha256 of url field if non-empty'),
                'url' => array('type' => 'text', 'description' => 'URL of thumbnail'),
                'filename' => array('type' => 'text', 'description' => 'if stored locally, filename is put here'),
                'width' => array('type' => 'int', 'description' => 'width of thumbnail'),
                'height' => array('type' => 'int', 'description' => 'height of thumbnail'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('file_id', 'width', 'height'),
            'indexes' => array(
                'file_thumbnail_file_id_idx' => array('file_id'),
                'file_thumbnail_urlhash_idx' => array('urlhash'),
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
     *
     * @param   File    $file       The File object we're getting a thumbnail for.
     * @param   boolean $notNullUrl Originally remote thumbnails have a URL stored, we use this to find the "original"
     *
     * @return  File_thumbnail
     * @throws  NoResultException if no File_thumbnail matched the criteria
     */
    static function byFile(File $file, $notNullUrl=true) {
        $thumb = new File_thumbnail();
        $thumb->file_id = $file->getID();
        if ($notNullUrl) {
            $thumb->whereAdd('url IS NOT NULL');
        }
        $thumb->limit(1);
        if (!$thumb->find(true)) {
            throw new NoResultException($thumb);
        }
        return $thumb;
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
        File::tryFilename($filename);

        // NOTE: If this is left empty in default config, it will be set to File::path('thumb')
        $dir = common_config('thumbnail', 'dir');

        if (!in_array($dir[mb_strlen($dir)-1], ['/', '\\'])) {
            $dir .= DIRECTORY_SEPARATOR;
        }

        return $dir . $filename;
    }

    static function url($filename)
    {
        File::tryFilename($filename);

        // FIXME: private site thumbnails?

        $path = common_config('thumbnail', 'path');
        if (empty($path)) {
            return File::url('thumb')."/{$filename}";
        }

        $protocol = (GNUsocial::useHTTPS() ? 'https' : 'http');
        $server = common_config('thumbnail', 'server') ?: common_config('site', 'server');

        if ($path[mb_strlen($path)-1] != '/') {
            $path .= '/';
        }
        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        return $protocol.'://'.$server.$path.$filename;
    }

    public function getFilename()
    {
        return File::tryFilename($this->filename);
    }

    /**
     *
     * @return  string  full filesystem path to the locally stored thumbnail file
     * @throws  
     */
    public function getPath()
    {
        $oldpath = File::path($this->getFilename());
        $thumbpath = self::path($this->getFilename());

        // If we have a file in our old thumbnail storage path, move (or copy) it to the new one
        // (if the if/elseif don't match, we have a $thumbpath just as we should and can return it)
        if (file_exists($oldpath) && !file_exists($thumbpath)) {
            try {
                // let's get the filename of the File, to check below if it happens to be identical
                $file_filename = $this->getFile()->getFilename();
            } catch (NoResultException $e) {
                // reasonably the function calling us will handle the following as "File_thumbnail entry should be deleted"
                throw FileNotFoundException($thumbpath);
            } catch (InvalidFilenameException $e) {
                // invalid filename in getFile()->getFilename(), just
                // means the File object isn't stored locally and that
                // means it's safe to move it below.
                $file_filename = null;
            }

            if ($this->getFilename() === $file_filename) {
                // special case where thumbnail file exactly matches stored File filename
                common_debug('File filename and File_thumbnail filename match on '.$this->file_id.', copying instead');
                copy($oldpath, $thumbpath);
            } elseif (!rename($oldpath, $thumbpath)) {
                common_log(LOG_ERR, 'Could not move thumbnail from '._ve($oldpath).' to '._ve($thumbpath));
                throw new ServerException('Could not move thumbnail from old path to new path.');
            } else {
                common_log(LOG_DEBUG, 'Moved thumbnail '.$this->file_id.' from '._ve($oldpath).' to '._ve($thumbpath));
            }
        } elseif (!file_exists($thumbpath)) {
            throw new FileNotFoundException($thumbpath);
        }

        return $thumbpath;
    }

    public function getUrl()
    {
        if (!empty($this->filename) || $this->getFile()->isLocal()) {
            // A locally stored File, so we can dynamically generate a URL.
            $url = common_local_url('attachment_thumbnail', array('attachment'=>$this->file_id));
            if (strpos($url, '?') === false) {
                $url .= '?';
            }
            return $url . http_build_query(array('w'=>$this->width, 'h'=>$this->height));
        }

        // No local filename available, return the remote URL we have stored
        return $this->url;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @throws UseFileAsThumbnailException from File_thumbnail->getUrl() for stuff like animated GIFs
     */
    public function getHtmlAttrs(array $orig=array(), $overwrite=true)
    {
        $attrs = [
                'height' => $this->getHeight(),
                'width'  => $this->getWidth(),
                'src'    => $this->getUrl(),
            ];
        return $overwrite ? array_merge($orig, $attrs) : array_merge($attrs, $orig);
    }

    public function delete($useWhere=false)
    {
        try {
            $thumbpath = self::path($this->getFilename());
            // if file does not exist, try to delete it
            $deleted = !file_exists($thumbpath) || @unlink($thumbpath);
            if (!$deleted) {
                common_log(LOG_ERR, 'Could not unlink existing thumbnail file: '._ve($thumbpath));
            }
        } catch (InvalidFilenameException $e) {
            common_log(LOG_ERR, 'Deleting object but not attempting deleting file: '._ve($e->getMessage()));
        }

        return parent::delete($useWhere);
    }

    public function getFile()
    {
        return File::getByID($this->file_id);
    }


    static public function hashurl($url)
    {
        if (!mb_strlen($url)) {
            throw new Exception('No URL provided to hash algorithm.');
        }
        return hash(self::URLHASH_ALG, $url);
    }

    public function onInsert()
    {
        $this->setUrlhash();
    }

    public function onUpdate($dataObject=false)
    {
        // if we have nothing to compare with OR it has changed from previous entry
        if (!$dataObject instanceof Managed_DataObject || $this->url !== $dataObject->url) {
            $this->setUrlhash();
        }
    }

    public function setUrlhash()
    {
        $this->urlhash = mb_strlen($this->url)>0 ? self::hashurl($this->url) : null;
    }
}

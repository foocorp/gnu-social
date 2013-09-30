<?php
/**
 * Table Definition for avatar
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Avatar extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'avatar';                          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $original;                        // tinyint(1)
    public $width;                           // int(4)  primary_key not_null
    public $height;                          // int(4)  primary_key not_null
    public $mediatype;                       // varchar(32)   not_null
    public $filename;                        // varchar(255)
    public $url;                             // varchar(255)  unique_key
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
                'original' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'uploaded by user or generated?'),
                'width' => array('type' => 'int', 'not null' => true, 'description' => 'image width'),
                'height' => array('type' => 'int', 'not null' => true, 'description' => 'image height'),
                'mediatype' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'file type'),
                'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'local filename, if local'),
                'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'avatar location'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('profile_id', 'width', 'height'),
            'unique keys' => array(
                'avatar_url_key' => array('url'),
            ),
            'foreign keys' => array(
                'avatar_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                'avatar_profile_id_idx' => array('profile_id'),
            ),
        );
    }
    // We clean up the file, too

    function delete()
    {
        $filename = $this->filename;
        if (parent::delete()) {
            @unlink(Avatar::path($filename));
        }
    }

    public static function deleteFromProfile(Profile $target) {
        $avatars = Avatar::getProfileAvatars($target->id);
        foreach ($avatars as $avatar) {
            $avatar->delete();
        }
    }

    public static function getOriginal(Profile $target)
    {
        $avatar = new Avatar();
        $avatar->profile_id = $target->id;
        $avatar->original = true;
        if (!$avatar->find(true)) {
            throw new NoResultException($avatar);
        }
        return $avatar;
    }

    public static function getProfileAvatars(Profile $target) {
        $avatar = new Avatar();
        $avatar->profile_id = $target->id;
        return $avatar->fetchAll();
    }

    /**
     * Where should the avatar go for this user?
     */
    static function filename($id, $extension, $size=null, $extra=null)
    {
        if ($size) {
            return $id . '-' . $size . (($extra) ? ('-' . $extra) : '') . $extension;
        } else {
            return $id . '-original' . (($extra) ? ('-' . $extra) : '') . $extension;
        }
    }

    static function path($filename)
    {
        $dir = common_config('avatar', 'dir');

        if ($dir[strlen($dir)-1] != '/') {
            $dir .= '/';
        }

        return $dir . $filename;
    }

    static function url($filename)
    {
        $path = common_config('avatar', 'path');

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        $server = common_config('avatar', 'server');

        if (empty($server)) {
            $server = common_config('site', 'server');
        }

        $ssl = common_config('avatar', 'ssl');

        if (is_null($ssl)) { // null -> guess
            if (common_config('site', 'ssl') == 'always' &&
                !common_config('avatar', 'server')) {
                $ssl = true;
            } else {
                $ssl = false;
            }
        }

        $protocol = ($ssl) ? 'https' : 'http';

        return $protocol.'://'.$server.$path.$filename;
    }

    function displayUrl()
    {
        $server = common_config('avatar', 'server');
        if ($server && !empty($this->filename)) {
            return Avatar::url($this->filename);
        } else {
            return $this->url;
        }
    }

    static function defaultImage($size)
    {
        static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
                                  AVATAR_STREAM_SIZE => 'stream',
                                  AVATAR_MINI_SIZE => 'mini');
        return Theme::path('default-avatar-'.$sizenames[$size].'.png');
    }

    static function newSize(Profile $target, $size) {
        $size = floor($size);
        if ($size <1 || $size > 999) {
            // TRANS: An error message when avatar size is unreasonable
            throw new Exception(_m('Unreasonable avatar size'));
        }

        $original = Avatar::getOriginal($target);

        $imagefile = new ImageFile($target->id, Avatar::path($original->filename));
        $filename = $imagefile->resize($size);

        $scaled = clone($original);
        $scaled->original = false;
        $scaled->width = $size;
        $scaled->height = $size;
        $scaled->url = Avatar::url($filename);
        $scaled->created = DB_DataObject_Cast::dateTime();

        if (!$scaled->insert()) {
            // TRANS: An error message when unable to insert avatar data into the db
            throw new Exception(_m('Could not insert new avatar data to database'));
        }

        // Return the new avatar object
        return $scaled;
    }
}

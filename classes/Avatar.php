<?php
/**
 * Table Definition for avatar
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Avatar extends Memcached_DataObject
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

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Avatar',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    # We clean up the file, too

    function delete()
    {
        $filename = $this->filename;
        if (parent::delete()) {
            @unlink(Avatar::path($filename));
        }
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Avatar', $kv);
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
        if ($server) {
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
}

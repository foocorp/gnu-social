<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

// XXX: Arg! This wouldn't be necessary if we used Avatars conistently
class AvatarLink
{
    public $url;
    public $type;
    public $size;
    public $width;
    public $height;

    function __construct($element=null)
    {
        if ($element) {
            // @fixme use correct namespaces
            $this->url = $element->getAttribute('href');
            $this->type = $element->getAttribute('type');
            $width = $element->getAttribute('media:width');
            if ($width != null) {
                $this->width = intval($width);
            }
            $height = $element->getAttribute('media:height');
            if ($height != null) {
                $this->height = intval($height);
            }
        }
    }

    static function fromAvatar(Avatar $avatar)
    {
        $alink = new AvatarLink();
        $alink->type   = $avatar->mediatype;
        $alink->height = $avatar->height;
        $alink->width  = $avatar->width;
        $alink->url    = $avatar->displayUrl();
        return $alink;
    }

    static function fromFilename($filename, $size)
    {
        $alink = new AvatarLink();
        $alink->url    = $filename;
        $alink->height = $size;
        $alink->width  = $size;
        if (!empty($filename)) {
            $alink->type   = self::mediatype($filename);
        } else {
            $alink->url    = User_group::defaultLogo($size);
            $alink->type   = 'image/png';
        }
        return $alink;
    }

    // yuck!
    static function mediatype($filename) {
        $ext = strtolower(end(explode('.', $filename)));
        if ($ext == 'jpeg') {
            $ext = 'jpg';
        }
        // hope we don't support any others
        $types = array('png', 'gif', 'jpg', 'jpeg');
        if (in_array($ext, $types)) {
            return 'image/' . $ext;
        }
        return null;
    }
}

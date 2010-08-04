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

class PhotosAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->user = common_current_user();
        common_log(LOG_INFO, "finishing prepare. user : ");
        common_log(LOG_INFO, $this->user->nickname);
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        if (empty($this->user)) {
            return _m('Hello');
        } else {
            return sprintf(_m('Hello, %s'), $this->user->nickname);
        }
    }


    function showContent()
    {
        common_log(LOG_INFO, "getting to show content.\n");
        if (empty($this->user)) {
            // TODO: should just redirect to the login page.
            $this->element('p', array(), 'You are not logged in');
        } else {
            common_log(LOG_INFO, 'fileroot : ' . $_SERVER['DOCUMENT_ROOT'] . '/file/');
            $dir = opendir($_SERVER['DOCUMENT_ROOT'] . '/file/');
            if ($dir === false) {
                $err = error_get_last();
                common_log(LOG_INFO, 'Error opening dir : ' . $err['message']);
                return;
            }
            $args = $this->returnToArgs();
            foreach (array_keys($args) as $key) {
                common_log(LOG_INFO, $key . ' => ' . $args[$key]);
                if (is_array($args[$key])) {
                    foreach (array_keys($args[$key]) as $skey) {
                        common_log(LOG_INFO, '   ' . $skey . ' => ' . $args[$key][$skey]);
                    }
                }
            }
            $pathparts = explode('/', $args[1]['nickname']);
            $username = $pathparts[0];
            $this->elementStart('ul', array('class' => 'photothumbs'));
            while (false !== ($file = readdir($dir))) {
                $fparts = explode('-', $file);
                if ($fparts[0] == $username // uploaded by this user
                    && ((substr($file, -4) == '.png') 
                        || (substr($file, -4) == '.jpg') // XXX: is this needed? status.net seems to save jpgs as .jpeg
                        || (substr($file, -5) == '.jpeg')
                        || (substr($file, -4) == '.gif'))) { // and it's an image
                        common_log(LOG_INFO, 'file : ' . $file);
                        $this->elementStart('li');
                        $this->elementStart('a', array('href' => 'http://' . common_config('site', 'server') . '/file/' . $file));
                        if (!file_exists(INSTALLDIR . '/file/thumb.' . $file)) {
                            $this->makeThumb($file);
                        }
                        $this->element('img', array('src' => 'http://' . common_config('site', 'server') . '/file/' . 'thumb.' .  $file));
                        $this->elementEnd('a');
                        $this->elementEnd('li');
                }
            }
            $this->elementEnd('ul');
        }
    }

    function makeThumb($filename)
    {
        $height_dest = 192;
        $width_dest = 256;

        $size_src = getimagesize(INSTALLDIR . '/file/' . $filename);
        $image_type = $size_src[2];
        
        switch($image_type) {
        case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg(INSTALLDIR . '/file/' . $filename);
            break;
        case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng(INSTALLDIR . '/file/' . $filename);
            break;
        case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif(INSTALLDIR . '/file/' . $filename);
            break;
        default:
            return false;
        } 

        $width_src = $size_src[0];
        $height_src = $size_src[1];

        $ratio_src = (float) $width_src / (float) $height_src;
        $ratio_dest = (float) $width_dest / (float) $height_dest;

        if ($ratio_src > $ratio_dest) {
            $height_crop = $height_src;
            $width_crop = (int)($height_crop * $ratio_dest);
            $x_crop = ($width_src - $width_crop) / 2;
        } else {
            $width_crop = $width_src;
            $height_crop = (int)($width_crop / $ratio_dest);
            $x_crop = 0;
        }
        
        $image_dest = imagecreatetruecolor($width_dest, $height_dest);
        
        imagecopyresampled($image_dest, $image_src, 0, 0, $x_crop, 0, $width_dest, $height_dest, $width_crop, $height_crop);
        switch ($image_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($image_dest, INSTALLDIR . '/file/' . 'thumb.' . $filename, 100);
            break;
        case IMAGETYPE_PNG:
            imagepng($image_dest, INSTALLDIR . '/file/thumb.' . $filename);
            break;
        case IMAGETYPE_GIF:
            imagegif($image_dest, INSTALLDIR . '/file/thumb.' . $filename);
            break;
        }
        
        imagedestroy($image_src);
        imagedestroy($image_dest);

        return true;
    }
}

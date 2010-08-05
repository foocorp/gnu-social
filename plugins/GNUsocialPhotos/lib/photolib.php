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


function photo_make_thumbnail($filename)
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

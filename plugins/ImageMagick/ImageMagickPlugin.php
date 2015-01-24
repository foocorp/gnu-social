<?php
/**
 * GNU social - a federating social network
 *
 * Plugin to handle more kinds of image formats thanks to ImageMagick
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
 * @category  Plugin
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/*
 * Dependencies:
 *  php5-imagick
 *
 * Provides:
 *  Animated image support (for image/gif at least)
 *
 * Bugs:
 *  Not even ImageMagick is very good at resizing animated GIFs.
 */

class ImageMagickPlugin extends Plugin
{
    // configurable plugin options must be declared public
    public $resize_animated = false;

    /**
     * @param ImageFile $file An ImageFile object we're getting metadata for
     * @param array $info The response from getimagesize()
     */
    public function onFillImageFileMetadata(ImageFile $imagefile) {
        switch ($imagefile->type) {
        case IMAGETYPE_GIF:
            $magick = new Imagick($imagefile->filepath);
            $magick = $magick->coalesceImages();
            $imagefile->animated = $magick->getNumberImages()>1;
            return false;
        }

        return true;
    }

    public function onStartResizeImageFile(ImageFile $imagefile, $outpath, array $box) {
        // So far we only take over the resize for IMAGETYPE_GIF
        // (and only animated for gifs!)
        if ($imagefile->type == IMAGETYPE_GIF && $imagefile->animated) {
            if (!$this->resize_animated) {
                // Don't resize if not desired, due to CPU/time limitations
                return false;
            }
            $magick = new Imagick($imagefile->filepath);
            $magick = $magick->coalesceImages();
            $magick->setIteratorIndex(0);
            do {
                $magick->cropImage($box['w'], $box['h'], $box['x'], $box['y']);
                $magick->thumbnailImage($box['width'], $box['height']);
                $magick->setImagePage($box['width'], $box['height'], 0, 0);
            } while ($magick->nextImage());
            $magick = $magick->deconstructImages();

            // $magick->writeImages($outpath, true); did not work, had to use filehandle
            // There's been bugs for writeImages in php5-imagick before, probably now too
            $fh = fopen($outpath, 'w+');
            $success = $magick->writeImagesFile($fh);
            fclose($fh);

            return !$success;
        }
        return true;
    }

    public function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ImageMagick',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/social',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use ImageMagick for better image support.'));
        return true;
    }
}


<?php
/**
 * GNU social - a federating social network
 *
 * Plugin to make thumbnails of video files with ffmpeg
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
 *  php5-ffmpeg
 *  php5-gd
 *
 * Video support will depend on your ffmpeg.
 */

class VideoThumbnailsPlugin extends Plugin
{
    /*
     * This function should only extract an image from the video stream
     * and disregard any scaling aspects in the resulting file, since
     * this will be handled in the core thumbnail algorithm.
     */
    public function onCreateFileImageThumbnail(MediaFile $file, &$imgPath, $media=null)
    {
        // This might accidentally pass application/ogg videos.
        // If that's a problem, let's fix it in the calling function.
        if ($media !== 'video') {
            return true;
        }

        $movie = new ffmpeg_movie($file->getPath(), false);

        $frames = $movie->getFrameCount();
        if ($frames > 0) {
            $frame = $movie->getFrame(floor($frames/2));
        } else {
            $frame = $movie->getNextKeyFrame();
        }

        // We failed to get a frame.
        if ($frame === null) {
            return true;
        }

        // Let's save our frame to a temporary file. If we fail, remove it.
        $imgPath = tempnam(sys_get_temp_dir(), 'socialthumb');
        if (!imagejpeg($frame->toGDImage(), $imgPath)) {
            @unlink($imgPath);
            return true;
        }
        return false;
    }

    public function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Video Thumbnails',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://www.gnu.org/software/social/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('HTML5 media attachment support'));
        return true;
    }
}


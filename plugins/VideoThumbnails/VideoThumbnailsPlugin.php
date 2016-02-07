<?php
/**
 * GNU social - a federating social network
 *
 * Plugin to make thumbnails of video files with ffmpeg/avconv
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
 *  ffmpeg/avconv (external program call)
 *  php5-gd
 *
 * Todo:
 *  Make sure we support ffmpeg too, so we're not super Debian oriented.
 *
 * Video support will depend on your ffmpeg/avconv.
 */

class VideoThumbnailsPlugin extends Plugin
{
    /*
     * This function should only extract an image from the video stream
     * and disregard any cropping or scaling in the resulting file, as
     * that will be handled in the core thumbnail algorithm.
     */
    public function onCreateFileImageThumbnailSource(File $file, &$imgPath, $media=null)
    {
        // The calling function might accidentally pass application/ogg videos.
        // If that's a problem, let's fix it in the calling function.
        if ($media !== 'video' || empty($file->filename)) {
            return true;
        }

        // Let's save our frame to a temporary file. If we fail, remove it.
        $tmp_imgPath = tempnam(sys_get_temp_dir(), 'socialthumb-');

        $cmd = null;
        if (shell_exec('which ffmpeg')) {
            $cmd = 'ffmpeg';
        } elseif (shell_exec('which avconv')) {
            $cmd = 'avconv';
        } else {
            common_log(LOG_ERR, 'Neither ffmpeg nor avconv was found in your PATH. Cannot create video thumbnail.');
            return true;
        }
        $result = exec($cmd.' -i '.escapeshellarg($file->getPath()).' -vcodec mjpeg -vframes 1 -f image2 -an '.escapeshellarg($tmp_imgPath));

        if (!getimagesize($tmp_imgPath)) {
            common_debug('exec of "avconv" produced a bad/nonexisting image it seems');
            @unlink($tmp_imgPath);
            return true;
        }
        $imgPath = $tmp_imgPath;
        return false;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Video Thumbnails',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://www.gnu.org/software/social/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Video thumbnail preview support.'));
        return true;
    }
}


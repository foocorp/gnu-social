<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstraction for an image file
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
 * @category  Image
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * A wrapper on uploaded files
 *
 * Makes it slightly easier to accept an image file from upload.
 *
 * @category Image
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ImageFile
{
    var $id;
    var $filepath;
    var $filename;
    var $type;
    var $height;
    var $width;
    var $rotate=0;  // degrees to rotate for properly oriented image (extrapolated from EXIF etc.)
    var $animated = null;  // Animated image? (has more than 1 frame). null means untested
    var $mimetype = null;   // The _ImageFile_ mimetype, _not_ the originating File object

    protected $fileRecord = null;

    function __construct($id, $filepath)
    {
        $this->id = $id;
        if (!empty($this->id)) {
            $this->fileRecord = new File();
            $this->fileRecord->id = $this->id;
            if (!$this->fileRecord->find(true)) {
                // If we have set an ID, we need that ID to exist!
                throw new NoResultException($this->fileRecord);
            }
        }

        // These do not have to be the same as fileRecord->filename for example,
        // since we may have generated an image source file from something else!
        $this->filepath = $filepath;
        $this->filename = basename($filepath);

        $info = @getimagesize($this->filepath);

        if (!(
            ($info[2] == IMAGETYPE_GIF && function_exists('imagecreatefromgif')) ||
            ($info[2] == IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) ||
            $info[2] == IMAGETYPE_BMP ||
            ($info[2] == IMAGETYPE_WBMP && function_exists('imagecreatefromwbmp')) ||
            ($info[2] == IMAGETYPE_XBM && function_exists('imagecreatefromxbm')) ||
            ($info[2] == IMAGETYPE_PNG && function_exists('imagecreatefrompng')))) {

            // TRANS: Exception thrown when trying to upload an unsupported image file format.
            throw new UnsupportedMediaException(_('Unsupported image format.'), $this->filepath);
        }

        $this->width    = $info[0];
        $this->height   = $info[1];
        $this->type     = $info[2];
        $this->mimetype = $info['mime'];

        if ($this->type == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            // Orientation value to rotate thumbnails properly
            $exif = exif_read_data($this->filepath);
            if (is_array($exif) && isset($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                case 1: // top is top
                    $this->rotate = 0;
                    break;
                case 3: // top is bottom
                    $this->rotate = 180;
                    break;
                case 6: // top is right
                    $this->rotate = -90;
                    break;
                case 8: // top is left
                    $this->rotate = 90;
                    break;
                }
                // If we ever write this back, Orientation should be set to '1'
            }
        } elseif ($this->type === IMAGETYPE_GIF) {
            $this->animated = $this->isAnimatedGif();
        }

        Event::handle('FillImageFileMetadata', array($this));
    }

    public static function fromFileObject(File $file)
    {
        $imgPath = null;
        $media = common_get_mime_media($file->mimetype);
        if (Event::handle('CreateFileImageThumbnailSource', array($file, &$imgPath, $media))) {
            if (empty($file->filename) && !file_exists($imgPath)) {
                throw new UnsupportedMediaException(_('File without filename could not get a thumbnail source.'));
            }

            // First some mimetype specific exceptions
            switch ($file->mimetype) {
            case 'image/svg+xml':
                throw new UseFileAsThumbnailException($file->id);
            }

            // And we'll only consider it an image if it has such a media type
            switch ($media) {
            case 'image':
                $imgPath = $file->getPath();
                break;
            default:
                throw new UnsupportedMediaException(_('Unsupported media format.'), $file->getPath());
            }
        }

        if (!file_exists($imgPath)) {
            throw new ServerException(sprintf('Image not available locally: %s', $imgPath));
        }

        try {
            $image = new ImageFile($file->id, $imgPath);
        } catch (UnsupportedMediaException $e) {
            // Avoid deleting the original
            if ($imgPath != $file->getPath()) {
                unlink($imgPath);
            }
            throw $e;
        }
        return $image;
    }

    public function getPath()
    {
        if (!file_exists($this->filepath)) {
            throw new FileNotFoundException($this->filepath);
        }

        return $this->filepath;
    }

    static function fromUpload($param='upload')
    {
        switch ($_FILES[$param]['error']) {
         case UPLOAD_ERR_OK: // success, jump out
            break;

         case UPLOAD_ERR_INI_SIZE:
         case UPLOAD_ERR_FORM_SIZE:
            // TRANS: Exception thrown when too large a file is uploaded.
            // TRANS: %s is the maximum file size, for example "500b", "10kB" or "2MB".
            throw new Exception(sprintf(_('That file is too big. The maximum file size is %s.'), ImageFile::maxFileSize()));

         case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES[$param]['tmp_name']);
            // TRANS: Exception thrown when uploading an image and that action could not be completed.
            throw new Exception(_('Partial upload.'));

         case UPLOAD_ERR_NO_FILE:
            // No file; probably just a non-AJAX submission.
            throw new ClientException(_('No file uploaded.'));

         default:
            common_log(LOG_ERR, __METHOD__ . ": Unknown upload error " . $_FILES[$param]['error']);
            // TRANS: Exception thrown when uploading an image fails for an unknown reason.
            throw new Exception(_('System error uploading file.'));
        }

        $info = @getimagesize($_FILES[$param]['tmp_name']);

        if (!$info) {
            @unlink($_FILES[$param]['tmp_name']);
            // TRANS: Exception thrown when uploading a file as image that is not an image or is a corrupt file.
            throw new UnsupportedMediaException(_('Not an image or corrupt file.'), '[deleted]');
        }

        return new ImageFile(null, $_FILES[$param]['tmp_name']);
    }

    /**
     * Copy the image file to the given destination.
     *
     * This function may modify the resulting file. Please use the
     * returned ImageFile object to read metadata (width, height etc.)
     *
     * @param string $outpath
     * @return ImageFile the image stored at target path
     */
    function copyTo($outpath)
    {
        return new ImageFile(null, $this->resizeTo($outpath));
    }

    /**
     * Create and save a thumbnail image.
     *
     * @param string $outpath
     * @param array $box    width, height, boundary box (x,y,w,h) defaults to full image
     * @return string full local filesystem filename
     */
    function resizeTo($outpath, array $box=array())
    {
        $box['width'] = isset($box['width']) ? intval($box['width']) : $this->width;
        $box['height'] = isset($box['height']) ? intval($box['height']) : $this->height;
        $box['x'] = isset($box['x']) ? intval($box['x']) : 0;
        $box['y'] = isset($box['y']) ? intval($box['y']) : 0;
        $box['w'] = isset($box['w']) ? intval($box['w']) : $this->width;
        $box['h'] = isset($box['h']) ? intval($box['h']) : $this->height;

        if (!file_exists($this->filepath)) {
            // TRANS: Exception thrown during resize when image has been registered as present, but is no longer there.
            throw new Exception(_('Lost our file.'));
        }

        // Don't rotate/crop/scale if it isn't necessary
        if ($box['width'] === $this->width
                && $box['height'] === $this->height
                && $box['x'] === 0
                && $box['y'] === 0
                && $box['w'] === $this->width
                && $box['h'] === $this->height
                && $this->type == $this->preferredType()) {
            if ($this->rotate == 0) {
                // No rotational difference, just copy it as-is
                @copy($this->filepath, $outpath);
                return $outpath;
            } elseif (abs($this->rotate) == 90) {
                // Box is rotated 90 degrees in either direction,
                // so we have to redefine x to y and vice versa.
                $tmp = $box['width'];
                $box['width'] = $box['height'];
                $box['height'] = $tmp;
                $tmp = $box['x'];
                $box['x'] = $box['y'];
                $box['y'] = $tmp;
                $tmp = $box['w'];
                $box['w'] = $box['h'];
                $box['h'] = $tmp;
            }
        }


        if (Event::handle('StartResizeImageFile', array($this, $outpath, $box))) {
            $this->resizeToFile($outpath, $box);
        }

        if (!file_exists($outpath)) {
            throw new UseFileAsThumbnailException($this->id);
        }

        return $outpath;
    }

    protected function resizeToFile($outpath, array $box)
    {
        switch ($this->type) {
        case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif($this->filepath);
            break;
        case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg($this->filepath);
            break;
        case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng($this->filepath);
            break;
        case IMAGETYPE_BMP:
            $image_src = imagecreatefrombmp($this->filepath);
            break;
        case IMAGETYPE_WBMP:
            $image_src = imagecreatefromwbmp($this->filepath);
            break;
        case IMAGETYPE_XBM:
            $image_src = imagecreatefromxbm($this->filepath);
            break;
        default:
            // TRANS: Exception thrown when trying to resize an unknown file type.
            throw new Exception(_('Unknown file type'));
        }

        if ($this->rotate != 0) {
            $image_src = imagerotate($image_src, $this->rotate, 0);
        }

        $image_dest = imagecreatetruecolor($box['width'], $box['height']);

        if ($this->type == IMAGETYPE_GIF || $this->type == IMAGETYPE_PNG || $this->type == IMAGETYPE_BMP) {

            $transparent_idx = imagecolortransparent($image_src);

            if ($transparent_idx >= 0) {

                $transparent_color = imagecolorsforindex($image_src, $transparent_idx);
                $transparent_idx = imagecolorallocate($image_dest, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($image_dest, 0, 0, $transparent_idx);
                imagecolortransparent($image_dest, $transparent_idx);

            } elseif ($this->type == IMAGETYPE_PNG) {

                imagealphablending($image_dest, false);
                $transparent = imagecolorallocatealpha($image_dest, 0, 0, 0, 127);
                imagefill($image_dest, 0, 0, $transparent);
                imagesavealpha($image_dest, true);

            }
        }

        imagecopyresampled($image_dest, $image_src, 0, 0, $box['x'], $box['y'], $box['width'], $box['height'], $box['w'], $box['h']);

        switch ($this->preferredType()) {
         case IMAGETYPE_GIF:
            imagegif($image_dest, $outpath);
            break;
         case IMAGETYPE_JPEG:
            imagejpeg($image_dest, $outpath, common_config('image', 'jpegquality'));
            break;
         case IMAGETYPE_PNG:
            imagepng($image_dest, $outpath);
            break;
         default:
            // TRANS: Exception thrown when trying resize an unknown file type.
            throw new Exception(_('Unknown file type'));
        }

        imagedestroy($image_src);
        imagedestroy($image_dest);
    }


    /**
     * Several obscure file types should be normalized to PNG on resize.
     *
     * @fixme consider flattening anything not GIF or JPEG to PNG
     * @return int
     */
    function preferredType()
    {
        if($this->type == IMAGETYPE_BMP) {
            //we don't want to save BMP... it's an inefficient, rare, antiquated format
            //save png instead
            return IMAGETYPE_PNG;
        } else if($this->type == IMAGETYPE_WBMP) {
            //we don't want to save WBMP... it's a rare format that we can't guarantee clients will support
            //save png instead
            return IMAGETYPE_PNG;
        } else if($this->type == IMAGETYPE_XBM) {
            //we don't want to save XBM... it's a rare format that we can't guarantee clients will support
            //save png instead
            return IMAGETYPE_PNG;
        }
        return $this->type;
    }

    function unlink()
    {
        @unlink($this->filepath);
    }

    static function maxFileSize()
    {
        $value = ImageFile::maxFileSizeInt();

        if ($value > 1024 * 1024) {
            $value = $value/(1024*1024);
            // TRANS: Number of megabytes. %d is the number.
            return sprintf(_m('%dMB','%dMB',$value),$value);
        } else if ($value > 1024) {
            $value = $value/1024;
            // TRANS: Number of kilobytes. %d is the number.
            return sprintf(_m('%dkB','%dkB',$value),$value);
        } else {
            // TRANS: Number of bytes. %d is the number.
            return sprintf(_m('%dB','%dB',$value),$value);
        }
    }

    static function maxFileSizeInt()
    {
        return min(ImageFile::strToInt(ini_get('post_max_size')),
                   ImageFile::strToInt(ini_get('upload_max_filesize')),
                   ImageFile::strToInt(ini_get('memory_limit')));
    }

    static function strToInt($str)
    {
        $unit = substr($str, -1);
        $num = substr($str, 0, -1);

        switch(strtoupper($unit)){
         case 'G':
            $num *= 1024;
         case 'M':
            $num *= 1024;
         case 'K':
            $num *= 1024;
        }

        return $num;
    }

    public function scaleToFit($maxWidth=null, $maxHeight=null, $crop=null)
    {
        return self::getScalingValues($this->width, $this->height,
                                        $maxWidth, $maxHeight, $crop, $this->rotate);
    }

    /*
     * Gets scaling values for images of various types. Cropping can be enabled.
     *
     * Values will scale _up_ to fit max values if cropping is enabled!
     * With cropping disabled, the max value of each axis will be respected.
     *
     * @param $width    int Original width
     * @param $height   int Original height
     * @param $maxW     int Resulting max width
     * @param $maxH     int Resulting max height
     * @param $crop     int Crop to the size (not preserving aspect ratio)
     */
    public static function getScalingValues($width, $height,
                                        $maxW=null, $maxH=null,
                                        $crop=null, $rotate=0)
    {
        $maxW = $maxW ?: common_config('thumbnail', 'width');
        $maxH = $maxH ?: common_config('thumbnail', 'height');
  
        if ($maxW < 1 || ($maxH !== null && $maxH < 1)) {
            throw new ServerException('Bad parameters for ImageFile::getScalingValues');
        } elseif ($maxH === null) {
            // if maxH is null, we set maxH to equal maxW and enable crop
            $maxH = $maxW;
            $crop = true;
        }

        // Because GD doesn't understand EXIF orientation etc.
        if (abs($rotate) == 90) {
            $tmp = $width;
            $width = $height;
            $height = $tmp;
        }
  
        // Cropping data (for original image size). Default values, 0 and null,
        // imply no cropping and with preserved aspect ratio (per axis).
        $cx = 0;    // crop x
        $cy = 0;    // crop y
        $cw = null; // crop area width
        $ch = null; // crop area height
  
        if ($crop) {
            $s_ar = $width / $height;
            $t_ar = $maxW / $maxH;

            $rw = $maxW;
            $rh = $maxH;

            // Source aspect ratio differs from target, recalculate crop points!
            if ($s_ar > $t_ar) {
                $cx = floor($width / 2 - $height * $t_ar / 2);
                $cw = ceil($height * $t_ar);
            } elseif ($s_ar < $t_ar) {
                $cy = floor($height / 2 - $width / $t_ar / 2);
                $ch = ceil($width / $t_ar);
            }
        } else {
            $rw = $maxW;
            $rh = ceil($height * $rw / $width);

            // Scaling caused too large height, decrease to max accepted value
            if ($rh > $maxH) {
                $rh = $maxH;
                $rw = ceil($width * $rh / $height);
            }
        }
        return array(intval($rw), intval($rh),
                    intval($cx), intval($cy),
                    is_null($cw) ? $width : intval($cw),
                    is_null($ch) ? $height : intval($ch));
    }

    /**
     * Animated GIF test, courtesy of frank at huddler dot com et al:
     * http://php.net/manual/en/function.imagecreatefromgif.php#104473
     * Modified so avoid landing inside of a header (and thus not matching our regexp).
     */
    protected function isAnimatedGif()
    {
        if (!($fh = @fopen($this->filepath, 'rb'))) {
            return false;
        }

        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C)
        // In total the header is maximum 10 bytes.

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while(!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
            // rewind in case we ended up in the middle of the header, but avoid
            // infinite loop (i.e. don't rewind if we're already in the end).
            if (!feof($fh) && ftell($fh) >= 9) {
                fseek($fh, -9, SEEK_CUR);
            }
        }

        fclose($fh);
        return $count > 1;
    }

    public function getFileThumbnail($width, $height, $crop)
    {
        if (!$this->fileRecord instanceof File) {
            throw new ServerException('No File object attached to this ImageFile object.');
        }

        if ($width === null) {
            $width = common_config('thumbnail', 'width');
            $height = common_config('thumbnail', 'height');
            $crop = common_config('thumbnail', 'crop');
        }

        if ($height === null) {
            $height = $width;
            $crop = true;
        }

        // Get proper aspect ratio width and height before lookup
        // We have to do it through an ImageFile object because of orientation etc.
        // Only other solution would've been to rotate + rewrite uploaded files
        // which we don't want to do because we like original, untouched data!
        list($width, $height, $x, $y, $w, $h) = $this->scaleToFit($width, $height, $crop);

        $thumb = File_thumbnail::pkeyGet(array(
                                            'file_id'=> $this->fileRecord->id,
                                            'width'  => $width,
                                            'height' => $height,
                                        ));
        if ($thumb instanceof File_thumbnail) {
            return $thumb;
        }

        $filename = $this->fileRecord->filehash ?: $this->filename;    // Remote files don't have $this->filehash
        $extension = File::guessMimeExtension($this->mimetype);
        $outname = "thumb-{$this->fileRecord->id}-{$width}x{$height}-{$filename}." . $extension;
        $outpath = File_thumbnail::path($outname);

        // The boundary box for our resizing
        $box = array('width'=>$width, 'height'=>$height,
                     'x'=>$x,         'y'=>$y,
                     'w'=>$w,         'h'=>$h);

        // Doublecheck that parameters are sane and integers.
        if ($box['width'] < 1 || $box['width'] > common_config('thumbnail', 'maxsize')
                || $box['height'] < 1 || $box['height'] > common_config('thumbnail', 'maxsize')
                || $box['w'] < 1 || $box['x'] >= $this->width
                || $box['h'] < 1 || $box['y'] >= $this->height) {
            // Fail on bad width parameter. If this occurs, it's due to algorithm in ImageFile->scaleToFit
            common_debug("Boundary box parameters for resize of {$this->filepath} : ".var_export($box,true));
            throw new ServerException('Bad thumbnail size parameters.');
        }

        common_debug(sprintf('Generating a thumbnail of File id==%u of size %ux%u', $this->fileRecord->id, $width, $height));

        // Perform resize and store into file
        $this->resizeTo($outpath, $box);

        // Avoid deleting the original
        if ($this->getPath() != File_thumbnail::path($this->filename)) {
            $this->unlink();
        }
        return File_thumbnail::saveThumbnail($this->fileRecord->id,
                                      File_thumbnail::url($outname),
                                      $width, $height,
                                      $outname);
    }
}

//PHP doesn't (as of 2/24/2010) have an imagecreatefrombmp so conditionally define one
if(!function_exists('imagecreatefrombmp')){
    //taken shamelessly from http://www.php.net/manual/en/function.imagecreatefromwbmp.php#86214
    function imagecreatefrombmp($p_sFile)
    {
        //    Load the image into a string
        $file    =    fopen($p_sFile,"rb");
        $read    =    fread($file,10);
        while(!feof($file)&&($read<>""))
            $read    .=    fread($file,1024);

        $temp    =    unpack("H*",$read);
        $hex    =    $temp[1];
        $header    =    substr($hex,0,108);

        //    Process the header
        //    Structure: http://www.fastgraph.com/help/bmp_header_format.html
        if (substr($header,0,4)=="424d")
        {
            //    Cut it in parts of 2 bytes
            $header_parts    =    str_split($header,2);

            //    Get the width        4 bytes
            $width            =    hexdec($header_parts[19].$header_parts[18]);

            //    Get the height        4 bytes
            $height            =    hexdec($header_parts[23].$header_parts[22]);

            //    Unset the header params
            unset($header_parts);
        }

        //    Define starting X and Y
        $x                =    0;
        $y                =    1;

        //    Create newimage
        $image            =    imagecreatetruecolor($width,$height);

        //    Grab the body from the image
        $body            =    substr($hex,108);

        //    Calculate if padding at the end-line is needed
        //    Divided by two to keep overview.
        //    1 byte = 2 HEX-chars
        $body_size        =    (strlen($body)/2);
        $header_size    =    ($width*$height);

        //    Use end-line padding? Only when needed
        $usePadding        =    ($body_size>($header_size*3)+4);

        //    Using a for-loop with index-calculation instaid of str_split to avoid large memory consumption
        //    Calculate the next DWORD-position in the body
        for ($i=0;$i<$body_size;$i+=3)
        {
            //    Calculate line-ending and padding
            if ($x>=$width)
            {
                //    If padding needed, ignore image-padding
                //    Shift i to the ending of the current 32-bit-block
                if ($usePadding)
                    $i    +=    $width%4;

                //    Reset horizontal position
                $x    =    0;

                //    Raise the height-position (bottom-up)
                $y++;

                //    Reached the image-height? Break the for-loop
                if ($y>$height)
                    break;
            }

            //    Calculation of the RGB-pixel (defined as BGR in image-data)
            //    Define $i_pos as absolute position in the body
            $i_pos    =    $i*2;
            $r        =    hexdec($body[$i_pos+4].$body[$i_pos+5]);
            $g        =    hexdec($body[$i_pos+2].$body[$i_pos+3]);
            $b        =    hexdec($body[$i_pos].$body[$i_pos+1]);

            //    Calculate and draw the pixel
            $color    =    imagecolorallocate($image,$r,$g,$b);
            imagesetpixel($image,$x,$height-$y,$color);

            //    Raise the horizontal position
            $x++;
        }

        //    Unset the body / free the memory
        unset($body);

        //    Return image-object
        return $image;
    }
}   // if(!function_exists('imagecreatefrombmp'))

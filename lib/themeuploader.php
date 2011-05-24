<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utilities for theme files and paths
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
 * @category  Paths
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Encapsulation of the validation-and-save process when dealing with
 * a user-uploaded StatusNet theme archive...
 *
 * @todo extract theme metadata from css/display.css
 * @todo allow saving multiple themes
 */
class ThemeUploader
{
    protected $sourceFile;
    protected $isUpload;
    private $prevErrorReporting;

    public function __construct($filename)
    {
        if (!class_exists('ZipArchive')) {
            // TRANS: Exception thrown when a compressed theme is uploaded while no support present in PHP configuration.
            throw new Exception(_('This server cannot handle theme uploads without ZIP support.'));
        }
        $this->sourceFile = $filename;
    }

    public static function fromUpload($name)
    {
        if (!isset($_FILES[$name]['error'])) {
            // TRANS: Server exception thrown when uploading a theme fails.
            throw new ServerException(_('The theme file is missing or the upload failed.'));
        }
        if ($_FILES[$name]['error'] != UPLOAD_ERR_OK) {
            // TRANS: Server exception thrown when uploading a theme fails.
            throw new ServerException(_('The theme file is missing or the upload failed.'));
        }
        return new ThemeUploader($_FILES[$name]['tmp_name']);
    }

    /**
     * @param string $destDir
     * @throws Exception on bogus files
     */
    public function extract($destDir)
    {
        $zip = $this->openArchive();

        // First pass: validate but don't save anything to disk.
        // Any errors will trip an exception.
        $this->traverseArchive($zip);

        // Second pass: now that we know we're good, actually extract!
        $tmpDir = $destDir . '.tmp' . getmypid();
        $this->traverseArchive($zip, $tmpDir);

        $zip->close();

        if (file_exists($destDir)) {
            $killDir = $tmpDir . '.old';
            $this->quiet();
            $ok = rename($destDir, $killDir);
            $this->loud();
            if (!$ok) {
                common_log(LOG_ERR, "Could not move old custom theme from $destDir to $killDir");
                // TRANS: Server exception thrown when saving an uploaded theme after decompressing it fails.
                throw new ServerException(_('Failed saving theme.'));
            }
        } else {
            $killDir = false;
        }

        $this->quiet();
        $ok = rename($tmpDir, $destDir);
        $this->loud();
        if (!$ok) {
            common_log(LOG_ERR, "Could not move saved theme from $tmpDir to $destDir");
            // TRANS: Server exception thrown when saving an uploaded theme after decompressing it fails.
            throw new ServerException(_('Failed saving theme.'));
        }

        if ($killDir) {
            $this->recursiveRmdir($killDir);
        }
    }

    /**
     *
     */
    protected function traverseArchive($zip, $outdir=false)
    {
        $sizeLimit = 2 * 1024 * 1024; // 2 megabyte space limit?
        $blockSize = 4096; // estimated; any entry probably takes this much space

        $totalSize = 0;
        $hasMain = false;
        $commonBaseDir = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $data = $zip->statIndex($i);
            $name = str_replace('\\', '/', $data['name']);

            if (substr($name, -1) == '/') {
                // A raw directory... skip!
                continue;
            }

            // Is this a safe or skippable file?
            $path = pathinfo($name);
            if ($this->skippable($path['filename'], $path['extension'])) {
                // Documentation and such... booooring
                continue;
            } else {
                $this->validateFile($path['filename'], $path['extension']);
            }

            // Check the directory structure...
            $dirs = explode('/', $path['dirname']);
            $baseDir = array_shift($dirs);
            if ($commonBaseDir === false) {
                $commonBaseDir = $baseDir;
            } else {
                if ($commonBaseDir != $baseDir) {
                    // TRANS: Server exception thrown when an uploaded theme has an incorrect structure.
                    throw new ClientException(_('Invalid theme: Bad directory structure.'));
                }
            }

            foreach ($dirs as $dir) {
                $this->validateFileOrFolder($dir);
            }

            $fullPath = $dirs;
            $fullPath[] = $path['basename'];
            $localFile = implode('/', $fullPath);
            if ($localFile == 'css/display.css') {
                $hasMain = true;
            }

            $size = $data['size'];
            $estSize = $blockSize * max(1, intval(ceil($size / $blockSize)));
            $totalSize += $estSize;
            if ($totalSize > $sizeLimit) {
                // TRANS: Client exception thrown when an uploaded theme is larger than the limit.
                // TRANS: %d is the number of bytes of the uncompressed theme.
                $msg = sprintf(_m('Uploaded theme is too large; must be less than %d byte uncompressed.',
                                  'Uploaded theme is too large; must be less than %d bytes uncompressed.',
                                  $sizeLimit),
                               $sizeLimit);
                throw new ClientException($msg);
            }

            if ($outdir) {
                $this->extractFile($zip, $data['name'], "$outdir/$localFile");
            }
        }

        if (!$hasMain) {
            // TRANS: Server exception thrown when an uploaded theme is incomplete.
            throw new ClientException(_('Invalid theme archive: ' .
                                        "Missing file css/display.css"));
        }
    }

    /**
     * @fixme Probably most unrecognized files should just be skipped...
     */
    protected function skippable($filename, $ext)
    {
        $skip = array('txt', 'html', 'rtf', 'doc', 'docx', 'odt', 'xcf');
        if (strtolower($filename) == 'readme') {
            return true;
        }
        if (in_array(strtolower($ext), $skip)) {
            return true;
        }
        if ($filename == '' || substr($filename, 0, 1) == '.') {
            // Skip Unix-style hidden files
            return true;
        }
        if ($filename == '__MACOSX') {
            // Skip awful metadata files Mac OS X slips in for you.
            // Thanks Apple!
            return true;
        }
        return false;
    }

    protected function validateFile($filename, $ext)
    {
        $this->validateFileOrFolder($filename);
        $this->validateExtension($filename, $ext);
        // @fixme validate content
    }

    protected function validateFileOrFolder($name)
    {
        if (!preg_match('/^[a-z0-9_\.-]+$/i', $name)) {
            common_log(LOG_ERR, "Bad theme filename: $name");
            // TRANS: Server exception thrown when an uploaded theme has an incorrect file or folder name.
            $msg = _("Theme contains invalid file or folder name. " .
                     'Stick with ASCII letters, digits, underscore, and minus sign.');
            throw new ClientException($msg);
        }
        if (preg_match('/\.(php|cgi|asp|aspx|js|vb)\w/i', $name)) {
            common_log(LOG_ERR, "Unsafe theme filename: $name");
            // TRANS: Server exception thrown when an uploaded theme contains files with unsafe file extensions.
            $msg = _('Theme contains unsafe file extension names; may be unsafe.');
            throw new ClientException($msg);
        }
        return true;
    }

    protected function validateExtension($base, $ext)
    {
        $allowed = array('css', // CSS may need validation
                         'png', 'gif', 'jpg', 'jpeg',
                         'svg', // SVG images/fonts may need validation
                         'ttf', 'eot', 'woff');
        if (!in_array(strtolower($ext), $allowed)) {
            if ($ext == 'ini' && $base == 'theme') {
                // theme.ini exception
                return true;
            }
            // TRANS: Server exception thrown when an uploaded theme contains a file type that is not allowed.
            // TRANS: %s is the file type that is not allowed.
            $msg = sprintf(_('Theme contains file of type ".%s", which is not allowed.'),
                           $ext);
            throw new ClientException($msg);
        }
        return true;
    }

    /**
     * @return ZipArchive
     */
    protected function openArchive()
    {
        $zip = new ZipArchive;
        $ok = $zip->open($this->sourceFile);
        if ($ok !== true) {
            common_log(LOG_ERR, "Error opening theme zip archive: " .
                                "{$this->sourceFile} code: {$ok}");
            // TRANS: Server exception thrown when an uploaded compressed theme cannot be opened.
            throw new Exception(_('Error opening theme archive.'));
        }
        return $zip;
    }

    /**
     * @param ZipArchive $zip
     * @param string $from original path inside ZIP archive
     * @param string $to final destination path in filesystem
     */
    protected function extractFile($zip, $from, $to)
    {
        $dir = dirname($to);
        if (!file_exists($dir)) {
            $this->quiet();
            $ok = mkdir($dir, 0755, true);
            $this->loud();
            if (!$ok) {
                common_log(LOG_ERR, "Failed to mkdir $dir while uploading theme");
                // TRANS: Server exception thrown when an uploaded theme cannot be saved during extraction.
                throw new ServerException(_('Failed saving theme.'));
            }
        } else if (!is_dir($dir)) {
            common_log(LOG_ERR, "Output directory $dir not a directory while uploading theme");
            // TRANS: Server exception thrown when an uploaded theme cannot be saved during extraction.
            throw new ServerException(_('Failed saving theme.'));
        }

        // ZipArchive::extractTo would be easier, but won't let us alter
        // the directory structure.
        $in = $zip->getStream($from);
        if (!$in) {
            common_log(LOG_ERR, "Couldn't open archived file $from while uploading theme");
            // TRANS: Server exception thrown when an uploaded theme cannot be saved during extraction.
            throw new ServerException(_('Failed saving theme.'));
        }
        $this->quiet();
        $out = fopen($to, "wb");
        $this->loud();
        if (!$out) {
            common_log(LOG_ERR, "Couldn't open output file $to while uploading theme");
            // TRANS: Server exception thrown when an uploaded theme cannot be saved during extraction.
            throw new ServerException(_('Failed saving theme.'));
        }
        while (!feof($in)) {
            $buffer = fread($in, 65536);
            fwrite($out, $buffer);
        }
        fclose($in);
        fclose($out);
    }

    private function quiet()
    {
        $this->prevErrorReporting = error_reporting();
        error_reporting($this->prevErrorReporting & ~E_WARNING);
    }

    private function loud()
    {
        error_reporting($this->prevErrorReporting);
    }

    private function recursiveRmdir($dir)
    {
        $list = dir($dir);
        while (($file = $list->read()) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $full = "$dir/$file";
            if (is_dir($full)) {
                $this->recursiveRmdir($full);
            } else {
                unlink($full);
            }
        }
        $list->close();
        rmdir($dir);
    }
}

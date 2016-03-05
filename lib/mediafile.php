<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstraction for media files in general
 *
 * TODO: combine with ImageFile?
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
 * @category  Media
 * @package   StatusNet
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class MediaFile
{
    var $filename      = null;
    var $fileRecord    = null;
    var $fileurl       = null;
    var $short_fileurl = null;
    var $mimetype      = null;

    function __construct($filename = null, $mimetype = null, $filehash = null)
    {
        $this->filename   = $filename;
        $this->mimetype   = $mimetype;
        $this->filehash   = $filehash;
        $this->fileRecord = $this->storeFile();

        $this->fileurl = common_local_url('attachment',
                                    array('attachment' => $this->fileRecord->id));

        $this->maybeAddRedir($this->fileRecord->id, $this->fileurl);
        $this->short_fileurl = common_shorten_url($this->fileurl);
        $this->maybeAddRedir($this->fileRecord->id, $this->short_fileurl);
    }

    public function attachToNotice(Notice $notice)
    {
        File_to_post::processNew($this->fileRecord, $notice);
    }

    public function getPath()
    {
        return File::path($this->filename);
    }

    function shortUrl()
    {
        return $this->short_fileurl;
    }

    function getEnclosure()
    {
        return $this->getFile()->getEnclosure();
    }

    function delete()
    {
        $filepath = File::path($this->filename);
        @unlink($filepath);
    }

    public function getFile()
    {
        if (!$this->fileRecord instanceof File) {
            throw new ServerException('File record did not exist for MediaFile');
        }

        return $this->fileRecord;
    }

    protected function storeFile()
    {
        $filepath       = File::path($this->filename);
        if (!empty($this->filename) && $this->filehash === null) {
            // Calculate if we have an older upload method somewhere (Qvitter) that
            // doesn't do this before calling new MediaFile on its local files...
            $this->filehash = hash_file(File::FILEHASH_ALG, $filepath);
            if ($this->filehash === false) {
                throw new ServerException('Could not read file for hashing');
            }
        }

        try {
            $file = File::getByHash($this->filehash);
            // We're done here. Yes. Already. We assume sha256 won't collide on us anytime soon.
            return $file;
        } catch (NoResultException $e) {
            // Well, let's just continue below.
        }

        $fileurl = File::url($this->filename);

        $file = new File;

        $file->filename = $this->filename;
        $file->urlhash  = File::hashurl($fileurl);
        $file->url      = $fileurl;
        $file->filehash = $this->filehash;
        $file->size     = filesize($filepath);
        if ($file->size === false) {
            throw new ServerException('Could not read file to get its size');
        }
        $file->date     = time();
        $file->mimetype = $this->mimetype;


        $file_id = $file->insert();

        if ($file_id===false) {
            common_log_db_error($file, "INSERT", __FILE__);
            // TRANS: Client exception thrown when a database error was thrown during a file upload operation.
            throw new ClientException(_('There was a database error while saving your file. Please try again.'));
        }

        // Set file geometrical properties if available
        try {
            $image = ImageFile::fromFileObject($file);
            $orig = clone($file);
            $file->width = $image->width;
            $file->height = $image->height;
            $file->update($orig);

            // We have to cleanup after ImageFile, since it
            // may have generated a temporary file from a
            // video support plugin or something.
            // FIXME: Do this more automagically.
            if ($image->getPath() != $file->getPath()) {
                $image->unlink();
            }
        } catch (ServerException $e) {
            // We just couldn't make out an image from the file. This
            // does not have to be UnsupportedMediaException, as we can
            // also get ServerException from files not existing etc.
        }

        return $file;
    }

    function rememberFile($file, $short)
    {
        $this->maybeAddRedir($file->id, $short);
    }

    function maybeAddRedir($file_id, $url)
    {
        try {
            $file_redir = File_redirection::getByUrl($url);
        } catch (NoResultException $e) {
            $file_redir = new File_redirection;
            $file_redir->urlhash = File::hashurl($url);
            $file_redir->url = $url;
            $file_redir->file_id = $file_id;

            $result = $file_redir->insert();

            if ($result===false) {
                common_log_db_error($file_redir, "INSERT", __FILE__);
                // TRANS: Client exception thrown when a database error was thrown during a file upload operation.
                throw new ClientException(_('There was a database error while saving your file. Please try again.'));
            }
        }
    }

    static function fromUpload($param='media', Profile $scoped=null)
    {
        // The existence of the "error" element means PHP has processed it properly even if it was ok.
        if (!isset($_FILES[$param]) || !isset($_FILES[$param]['error'])) {
            throw new NoUploadedMediaException($param);
        }

        switch ($_FILES[$param]['error']) {
            case UPLOAD_ERR_OK: // success, jump out
                break;
            case UPLOAD_ERR_INI_SIZE:
                // TRANS: Client exception thrown when an uploaded file is larger than set in php.ini.
                throw new ClientException(_('The uploaded file exceeds the ' .
                            'upload_max_filesize directive in php.ini.'));
            case UPLOAD_ERR_FORM_SIZE:
                throw new ClientException(
                        // TRANS: Client exception.
                        _('The uploaded file exceeds the MAX_FILE_SIZE directive' .
                            ' that was specified in the HTML form.'));
            case UPLOAD_ERR_PARTIAL:
                @unlink($_FILES[$param]['tmp_name']);
                // TRANS: Client exception.
                throw new ClientException(_('The uploaded file was only' .
                            ' partially uploaded.'));
            case UPLOAD_ERR_NO_FILE:
                // No file; probably just a non-AJAX submission.
                throw new NoUploadedMediaException($param);
            case UPLOAD_ERR_NO_TMP_DIR:
                // TRANS: Client exception thrown when a temporary folder is not present to store a file upload.
                throw new ClientException(_('Missing a temporary folder.'));
            case UPLOAD_ERR_CANT_WRITE:
                // TRANS: Client exception thrown when writing to disk is not possible during a file upload operation.
                throw new ClientException(_('Failed to write file to disk.'));
            case UPLOAD_ERR_EXTENSION:
                // TRANS: Client exception thrown when a file upload operation has been stopped by an extension.
                throw new ClientException(_('File upload stopped by extension.'));
            default:
                common_log(LOG_ERR, __METHOD__ . ": Unknown upload error " .
                        $_FILES[$param]['error']);
                // TRANS: Client exception thrown when a file upload operation has failed with an unknown reason.
                throw new ClientException(_('System error uploading file.'));
        }

        // TODO: Make documentation clearer that this won't work for files >2GiB because
        //       PHP is stupid in its 32bit head. But noone accepts 2GiB files with PHP
        //       anyway... I hope.
        $filehash = hash_file(File::FILEHASH_ALG, $_FILES[$param]['tmp_name']);

        try {
            $file = File::getByHash($filehash);
            // If no exception is thrown the file exists locally, so we'll use that and just add redirections.
            // but if the _actual_ locally stored file doesn't exist, getPath will throw FileNotFoundException
            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;

        } catch (FileNotFoundException $e) {
            // The file does not exist in our local filesystem, so store this upload.

            if (!move_uploaded_file($_FILES[$param]['tmp_name'], $e->path)) {
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_('File could not be moved to destination directory.'));
            }

            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;

        } catch (NoResultException $e) {
            // We have to save the upload as a new local file. This is the normal course of action.

            if ($scoped instanceof Profile) {
                // Throws exception if additional size does not respect quota
                // This test is only needed, of course, if we're uploading something new.
                File::respectsQuota($scoped, $_FILES[$param]['size']);
            }

            $mimetype = self::getUploadedMimeType($_FILES[$param]['tmp_name'], $_FILES[$param]['name']);
            $basename = basename($_FILES[$param]['name']);

            $filename = strtolower($filehash) . '.' . File::guessMimeExtension($mimetype, $basename);
            $filepath = File::path($filename);

            $result = move_uploaded_file($_FILES[$param]['tmp_name'], $filepath);

            if (!$result) {
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_('File could not be moved to destination directory.'));
            }
        }

        return new MediaFile($filename, $mimetype, $filehash);
    }

    static function fromFilehandle($fh, Profile $scoped=null) {
        $stream = stream_get_meta_data($fh);
        // So far we're only handling filehandles originating from tmpfile(),
        // so we can always do hash_file on $stream['uri'] as far as I can tell!
        $filehash = hash_file(File::FILEHASH_ALG, $stream['uri']);

        try {
            $file = File::getByHash($filehash);
            // Already have it, so let's reuse the locally stored File
            // by using getPath we also check whether the file exists
            // and throw a FileNotFoundException with the path if it doesn't.
            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;
        } catch (FileNotFoundException $e) {
            // This happens if the file we have uploaded has disappeared
            // from the local filesystem for some reason. Since we got the
            // File object from a sha256 check in fromFilehandle, it's safe
            // to just copy the uploaded data to disk!

            fseek($fh, 0);  // just to be sure, go to the beginning
            // dump the contents of our filehandle to the path from our exception
            // and report error if it failed.
            if (false === file_put_contents($e->path, fread($fh, filesize($stream['uri'])))) {
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_('File could not be moved to destination directory.'));
            }
            if (!chmod($e->path, 0664)) {
                common_log(LOG_ERR, 'Could not chmod uploaded file: '._ve($e->path));
            }

            $filename = basename($file->getPath());
            $mimetype = $file->mimetype;

        } catch (NoResultException $e) {
            if ($scoped instanceof Profile) {
                File::respectsQuota($scoped, filesize($stream['uri']));
            }

            $mimetype = self::getUploadedMimeType($stream['uri']);

            $filename = strtolower($filehash) . '.' . File::guessMimeExtension($mimetype);
            $filepath = File::path($filename);

            $result = copy($stream['uri'], $filepath) && chmod($filepath, 0664);

            if (!$result) {
                common_log(LOG_ERR, 'File could not be moved (or chmodded) from '._ve($stream['uri']) . ' to ' . _ve($filepath));
                // TRANS: Client exception thrown when a file upload operation fails because the file could
                // TRANS: not be moved from the temporary folder to the permanent file location.
                throw new ClientException(_('File could not be moved to destination directory.' ));
            }
        }

        return new MediaFile($filename, $mimetype, $filehash);
    }

    /**
     * Attempt to identify the content type of a given file.
     * 
     * @param string $filepath filesystem path as string (file must exist)
     * @param string $originalFilename (optional) for extension-based detection
     * @return string
     * 
     * @fixme this seems to tie a front-end error message in, kinda confusing
     * 
     * @throws ClientException if type is known, but not supported for local uploads
     */
    static function getUploadedMimeType($filepath, $originalFilename=false) {
        // We only accept filenames to existing files
        $mimelookup = new finfo(FILEINFO_MIME_TYPE);
        $mimetype = $mimelookup->file($filepath);

        // Unclear types are such that we can't really tell by the auto
        // detect what they are (.bin, .exe etc. are just "octet-stream")
        $unclearTypes = array('application/octet-stream',
                              'application/vnd.ms-office',
                              'application/zip',
                              'text/html',  // Ironically, Wikimedia Commons' SVG_logo.svg is identified as text/html
                              // TODO: for XML we could do better content-based sniffing too
                              'text/xml');

        $supported = common_config('attachments', 'supported');

        // If we didn't match, or it is an unclear match
        if ($originalFilename && (!$mimetype || in_array($mimetype, $unclearTypes))) {
            try {
                $type = common_supported_ext_to_mime($originalFilename);
                return $type;
            } catch (Exception $e) {
                // Extension not found, so $mimetype is our best guess
            }
        }

        // If $config['attachments']['supported'] equals boolean true, accept any mimetype
        if ($supported === true || array_key_exists($mimetype, $supported)) {
            // FIXME: Don't know if it always has a mimetype here because
            // finfo->file CAN return false on error: http://php.net/finfo_file
            // so if $supported === true, this may return something unexpected.
            return $mimetype;
        }

        // We can conclude that we have failed to get the MIME type
        $media = common_get_mime_media($mimetype);
        if ('application' !== $media) {
            // TRANS: Client exception thrown trying to upload a forbidden MIME type.
            // TRANS: %1$s is the file type that was denied, %2$s is the application part of
            // TRANS: the MIME type that was denied.
            $hint = sprintf(_('"%1$s" is not a supported file type on this server. ' .
            'Try using another %2$s format.'), $mimetype, $media);
        } else {
            // TRANS: Client exception thrown trying to upload a forbidden MIME type.
            // TRANS: %s is the file type that was denied.
            $hint = sprintf(_('"%s" is not a supported file type on this server.'), $mimetype);
        }
        throw new ClientException($hint);
    }
}

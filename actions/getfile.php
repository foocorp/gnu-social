<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Return a requested file
 *
 * PHP version 5
 *
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
 * @category  PrivateAttachments
 * @package   StatusNet
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * An action for returning a requested file
 *
 * The StatusNet system will do an implicit user check if the site is
 * private before allowing this to continue
 *
 * @category  PrivateAttachments
 * @package   StatusNet
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GetfileAction extends Action
{
    /**
     * Path of file to return
     */
    var $path = null;

    /**
     * Get file name
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $filename = $this->trimmed('filename');
        $path = null;

        if ($filename && File::validFilename($filename)) {
            $path = File::path($filename);
        }

        if (empty($path) or !file_exists($path)) {
            // TRANS: Client error displayed when requesting a non-existent file.
            $this->clientError(_('No such file.'), 404);
            return false;
        }
        if (!is_readable($path)) {
            // TRANS: Client error displayed when requesting a file without having read access to it.
            $this->clientError(_('Cannot read file.'), 403);
            return false;
        }

        $this->path = $path;
        return true;
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Last-modified date for file
     *
     * @return int last-modified date as unix timestamp
     */
    function lastModified()
    {
        if (common_config('site', 'use_x_sendfile')) {
            return null;
        }

        return filemtime($this->path);
    }

    /**
     * etag for file
     *
     * This returns the same data (inode, size, mtime) as Apache would,
     * but in decimal instead of hex.
     *
     * @return string etag http header
     */
    function etag()
    {
        if (common_config('site', 'use_x_sendfile')) {
            return null;
        }

        $cache = Cache::instance();
        if($cache) {
            $key = Cache::key('attachments:etag:' . $this->path);
            $etag = $cache->get($key);
            if($etag === false) {
                $etag = crc32(file_get_contents($this->path));
                $cache->set($key,$etag);
            }
            return $etag;
        }

        $stat = stat($this->path);
        return '"' . $stat['ino'] . '-' . $stat['size'] . '-' . $stat['mtime'] . '"';
    }

    /**
     * Handle input, produce output
     *
     * @return void
     */
    protected function handle()
    {
        // undo headers set by PHP sessions
        $sec = session_cache_expire() * 60;
        header('Expires: ' . date(DATE_RFC1123, time() + $sec));
        header('Cache-Control: max-age=' . $sec);

        parent::handle();

        $path = $this->path;

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        header('Content-Type: ' . $finfo->file($path));

        if (common_config('site', 'use_x_sendfile')) {
            header('X-Sendfile: ' . $path);
        } else {
            header('Content-Length: ' . filesize($path));
            readfile($path);
        }
    }
}

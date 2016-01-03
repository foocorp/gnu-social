<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Upload an image via the API
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
 * @category  API
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Upload an image via the API.  Returns a shortened URL for the image
 * to the user. Apparently modelled after a former Twitpic API.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiMediaUploadAction extends ApiAuthAction
{
    protected $needPost = true;

    /**
     * Handle the request
     *
     * Grab the file from the 'media' param, then store, and shorten
     *
     * @todo Upload throttle!
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
            // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
            // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
            $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                      intval($_SERVER['CONTENT_LENGTH']));
            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
        }

        // we could catch "NoUploadedMediaException" as "no media uploaded", but here we _always_ want an upload
        $upload = MediaFile::fromUpload('media', $this->scoped);
        
        // Thumbnails will be generated/cached on demand when accessed (such as with /attachment/:id/thumbnail)

        $this->showResponse($upload);
    }

    /**
     * Show a Twitpic-like response with the ID of the media file
     * and a (hopefully) shortened URL for it.
     *
     * @param MediaFile $upload  the uploaded file
     *
     * @return void
     */
    function showResponse(MediaFile $upload)
    {
        $this->initDocument();
        $this->elementStart('rsp', array('stat' => 'ok', 'xmlns:atom'=>Activity::ATOM));
        $this->element('mediaid', null, $upload->fileRecord->id);
        $this->element('mediaurl', null, $upload->shortUrl());

        $enclosure = $upload->fileRecord->getEnclosure();
        $this->element('atom:link', array('rel'  => 'enclosure',
                                          'href' => $enclosure->url,
                                          'type' => $enclosure->mimetype));
        $this->elementEnd('rsp');
        $this->endDocument();
    }

    /**
     * Overrided clientError to show a more Twitpic-like error
     *
     * @param String $msg an error message
     */
    function clientError($msg, $code=400, $format=null)
    {
        $this->initDocument();
        $this->elementStart('rsp', array('stat' => 'fail'));

        // @todo add in error code
        $errAttr = array('msg' => $msg);

        $this->element('err', $errAttr, null);
        $this->elementEnd('rsp');
        $this->endDocument();
        exit;
    }
}

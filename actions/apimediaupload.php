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

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        // fallback to xml for older clients etc
        if (empty($this->format)) {
            $this->format = 'xml';
        }
        if (!in_array($this->format, ['json', 'xml'])) {
            throw new ClientException('This API call does not support the format '._ve($this->format));
        }
        return true;
    }

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
            throw new ClientException(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
        }

        try {
            $upload = MediaFile::fromUpload('media', $this->scoped);
        } catch (NoUploadedMediaException $e) {
            common_debug('No media file was uploaded to the _FILES array');
            $fh = tmpfile();
            if ($this->arg('media')) {
                common_debug('Found media parameter which we hope contains a media file!');
                fwrite($fh, $this->arg('media'));
            } elseif ($this->arg('media_data')) {
                common_debug('Found media_data parameter which we hope contains a base64-encoded media file!');
                fwrite($fh, base64_decode($this->arg('media_data')));
            } else {
                common_debug('No media|media_data POST parameter was supplied');
                fclose($fh);
                throw $e;
            }
            common_debug('MediaFile importing the uploaded file with fromFilehandle');
            $upload = MediaFile::fromFilehandle($fh, $this->scoped);
        }
        
        common_debug('MediaFile completed and saved us fileRecord with id=='._ve($upload->fileRecord->id));
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
    protected function showResponse(MediaFile $upload)
    {
        $this->initDocument($this->format);
        switch ($this->format) {
        case 'json':
            return $this->showResponseJson($upload);
        case 'xml':
            return $this->showResponseXml($upload);
        default:
            throw new ClientException('This API call does not support the format '._ve($this->format));
        }
        $this->endDocument($this->format);
    }

    protected function showResponseJson(MediaFile $upload)
    {
        $enc = $upload->fileRecord->getEnclosure();

        // note that we use media_id instead of mediaid which XML users might've gotten used to (nowadays we service media_id in both!)
        $output = [
                'media_id' => $upload->fileRecord->id,
                'media_id_string' => (string)$upload->fileRecord->id,
                'media_url' => $upload->shortUrl(),
                'size' => $upload->fileRecord->size,
                ];
        if (common_get_mime_media($enc->mimetype) === 'image') {
            $output['image'] = [
                                'w' => $enc->width,
                                'h' => $enc->height,
                                'image_type' => $enc->mimetype,
                                ];
        }
        print json_encode($output);
    }

    protected function showResponseXml(MediaFile $upload)
    {
        $this->elementStart('rsp', array('stat' => 'ok', 'xmlns:atom'=>Activity::ATOM));
        $this->element('mediaid', null, $upload->fileRecord->id);
        $this->element('mediaurl', null, $upload->shortUrl());
        $this->element('media_url', null, $upload->shortUrl());
        $this->element('size', null, $upload->fileRecord->size);

        $enclosure = $upload->fileRecord->getEnclosure();
        $this->element('atom:link', array('rel'  => 'enclosure',
                                          'href' => $enclosure->url,
                                          'type' => $enclosure->mimetype));

        // Twitter specific metadata expected in response since Twitter's Media upload API v1.1 (even though Twitter doesn't use XML)
        $this->element('media_id', null, $upload->fileRecord->id);
        $this->element('media_id_string', null, (string)$upload->fileRecord->id);
        if (common_get_mime_media($enclosure->mimetype) === 'image') {
            $this->element('image', ['w'=>$enclosure->width, 'h'=>$enclosure->height, 'image_type'=>$enclosure->mimetype]);
        }
        $this->elementEnd('rsp');
    }

    /**
     * Overrided clientError to show a more Twitpic-like error
     *
     * @param String $msg an error message
     */
    function clientError($msg, $code=400, $format=null)
    {
        $this->initDocument($this->format);
        switch ($this->format) {
        case 'json':
            $error = ['errors' => array()];
            $error['errors'][] = ['message'=>$msg, 'code'=>131];
            print json_encode($error);
            break;
        case 'xml':
            $this->elementStart('rsp', array('stat' => 'fail'));

            // @todo add in error code
            $errAttr = array('msg' => $msg);

            $this->element('err', $errAttr, null);
            $this->elementEnd('rsp');
            break;
        }
        $this->endDocument($this->format);
        exit;
    }
}

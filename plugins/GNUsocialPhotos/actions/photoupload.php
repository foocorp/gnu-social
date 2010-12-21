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
 * @author    Sean Corbett <sean@gnu.org>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PhotouploadAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->user = common_current_user();
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        }
        $this->showPage();
    }

    function title()
    {
        return _m('Upload Photos');
    }

    function showContent()
    {
        if(empty($this->user)) {
            $this->element('p', array(), 'You are not logged in.');
        } else {
            $this->elementStart('form', array('enctype' => 'multipart/form-data',
                                              'method' => 'post',
                                              'action' => common_local_url('photoupload')));
            $this->element('input', array('name' => 'photofile',
                                          'type' => 'file',
                                          'id' => 'photofile'));
            $this->submit('upload', _('Upload'));
            $this->elementEnd('form');
        }
    }

    function handlePost()
    {

        common_log(LOG_INFO, 'handlPost()!');
        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
            $msg = _('The server was unable to handle that much POST ' .
                'data (%s bytes) due to its current configuration.');

            $this->showForm(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // CSRF protection

/*        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        } */

        if($this->arg('upload')) {
            $this->uploadPhoto();
        }
    }

    function showForm($msg, $success=false)
    { 
        $this->msg = $msg;
        $this->success = $success;

//        $this->showPage();
    }

    function uploadPhoto()
    {
        $cur = common_current_user();
        if(empty($cur)) {
            return;
        }
        try {
            $imagefile = ImageFile::fromUpload('photofile');
        } catch (Exception $e) {
            $this->showForm($e->getMessage());
            return;
        }
        if ($imagefile === null) {
            $this->showForm(_('No file uploaded.'));
            return;
        }

        common_log(LOG_INFO, 'upload path : ' . $imagefile->filepath);

        $filename = $cur->nickname . '-' . common_timestamp() . sha1_file($imagefile->filepath) .  image_type_to_extension($imagefile->type);
        move_uploaded_file($imagefile->filepath, INSTALLDIR . '/file/' . $filename);
        photo_make_thumbnail($filename);
        $uri = 'http://' . common_config('site', 'server') . '/file/' . $filename;
        $thumb_uri = 'http://' . common_config('site', 'server') . '/file/thumb.' . $filename;
        $profile_id = $cur->id;
       
        // TODO: proper multiple album support 
        $album = GNUsocialPhotoAlbum::staticGet('profile_id', $profile_id);
        if(!$album) {
            $album = GNUsocialPhotoAlbum::newAlbum($profile_id, 'Default');
            GNUsocialPhoto::saveNew($profile_id, $album->album_id, $thumb_uri, $uri, 'web', false);
        } else {
            GNUsocialPhoto::saveNew($profile_id, $album->album_id, $thumb_uri, $uri, 'web', false);
        }
    }

}

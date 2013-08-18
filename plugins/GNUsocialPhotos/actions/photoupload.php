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
 * @author    Max Shinn    <trombonechamp@gmail.com>
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
        //Upload a photo
        if(empty($this->user)) {
            $this->element('p', array(), 'You are not logged in.');
        } else {
            //showForm() data
            if(!empty($this->msg)) {
                $class = ($this->success) ? 'success' : 'error';
                $this->element('p', array('class' => $class), $this->msg);
            }

            $this->elementStart('form', array('enctype' => 'multipart/form-data',
                                              'method' => 'post',
                                              'action' => common_local_url('photoupload')));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->element('input', array('name' => 'photofile',
                                          'type' => 'file',
                                          'id' => 'photofile'));
            $this->elementEnd('li');
            //$this->element('br');
            $this->elementStart('li');
            $this->input('phototitle', _("Title"), null, _("The title of the photo. (Optional)"));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->textarea('photo_description', _("Description"), null, _("A description of the photo. (Optional)"));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->dropdown('album', _("Album"), $this->albumList(), _("The album in which to place this photo"), false);
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->submit('upload', _('Upload'));
            $this->elementEnd('form');
            $this->element('br');

            //Create a new album
            $this->element('h3', array(), _("Create a new album"));
            $this->elementStart('form', array('method' => 'post',
                                              'action' =>common_local_url('photoupload')));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->input('album_name', _("Title"), null, _("The title of the album."));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->textarea('album_description', _("Description"), null, _("A description of the album. (Optional)"));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->submit('create', _('Create'));
            $this->elementEnd('form');

            //Delete an album
            $this->element('h3', array(), _("Delete an album"));
            $this->elementStart('form', array('method' => 'post',
                                              'action' =>common_local_url('photoupload')));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->dropdown('album', _("Album"), $this->albumList(), _("The album in which to place this photo"), false);
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->submit('deletealbum', _('Delete'));
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

        if ($this->arg('upload')) {
            $this->uploadPhoto();
        }
        if ($this->arg('create')) {
            $this->createAlbum();
        }
        if ($this->arg('deletealbum')) {
            $this->deleteAlbum();
        }
    }

    function showForm($msg, $success=false)
    {
        $this->msg = $msg;
        $this->success = $success;

//        $this->showPage();
    }

    function albumList() 
    {
        $cur = common_current_user();
        $album = new GNUsocialPhotoAlbum();
        $album->user_id = $cur->id;

        $albumlist = array();
        if (!$album->find()) {
            GNUsocialPhotoAlbum::newAlbum($cur->id, 'Default');
        }
        while ($album->fetch()) {
            $albumlist[$album->album_id] = $album->album_name;
        }
        return $albumlist;
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

        $title = $this->trimmed('phototitle');
        $photo_description = $this->trimmed('photo_description');

        common_log(LOG_INFO, 'upload path : ' . $imagefile->filepath);

        $filename = $cur->nickname . '-' . common_timestamp() . sha1_file($imagefile->filepath) .  image_type_to_extension($imagefile->type);
        move_uploaded_file($imagefile->filepath, INSTALLDIR . '/file/' . $filename);
        photo_make_thumbnail($filename);
        $uri = 'http://' . common_config('site', 'server') . '/file/' . $filename;
        $thumb_uri = 'http://' . common_config('site', 'server') . '/file/thumb.' . $filename;
        $profile_id = $cur->id;
       
        $album = GNUsocialPhotoAlbum::getKV('album_id', $this->trimmed('album'));
        if ($album->profile_id != $profile_id) {
            $this->showForm(_('Error: This is not your album!'));
            return;
        }
        GNUsocialPhoto::saveNew($profile_id, $album->album_id, $thumb_uri, $uri, 'web', false, $title, $photo_description);
    }

    function createAlbum()
    {
        $cur = common_current_user();
        if(empty($cur)) {
            return;
        }

        $album_name = $this->trimmed('album_name');
        $album_description = $this->trimmed('album_description');
       
        GNUsocialPhotoAlbum::newAlbum($cur->id, $album_name, $album_description);
    }

    function deleteAlbum()
    {
        $cur = common_current_user();
        if(empty($cur)) return;
        
        $album_id = $this->trimmed('album');
        $album = GNUsocialPhotoAlbum::getKV('album_id', $album_id);
        if (empty($album)) {
            $this->showForm(_('This album does not exist or has been deleted.'));
            return;
        }
        //Check if the album has any photos in it before deleting
        $photos = GNUsocialPhoto::getKV('album_id', $album_id);
        if(empty($photos)) {
            $album->delete();
            $this->showForm(_('Album deleted'), true);
        }
        else {
            $this->showForm(_('Cannot delete album when there are photos in it!'), false);
        }
    }
}

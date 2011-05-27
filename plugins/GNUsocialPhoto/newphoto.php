<?php
/**
 * GNU Social
 * Copyright (C) 2011, Free Software Foundation, Inc.
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
 * @package   GNU Social
 * @author    Ian Denhardt <ian@zenhack.net>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if(!defined('STATUSNET')){
    exit(1);
}

class NewphotoAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->user = common_current_user();

        if(empty($this->user)){
            throw new ClientException(_('Must be logged in to post a photo'),
                403);
        }

        if($this->isPost()){
            $this->checkSessionToken();
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if ($this->isPost()) {
            $this->handlePost($args);
        } else {
            $this->showPage();
        }
    }
    
    function handlePost($args)
    {

        /*
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
        } */

        $profile = $this->user->getProfile();

        $options = array();
        
        ToSelector::fillOptions($this, $options);

        try {
            $this->handleUpload();
        } catch (Exception $e) {
            $this->showForm($e->getMessage());
            return;
        }
        

        common_redirect($photo->uri, 303);
    }

    function getUpload()
    {
            $imagefile = ImageFile::fromUpload('photo_upload');

            if($imagefile === null) {
                throw new Exception(_('No file uploaded'));
            }

            $title = $this->trimmed('title');
            $description = $this->trimmed('description');

            $new_filename = UUID::gen() . image_type_to_extension($imagefile->type);
            move_uploaded_file($imagefile->filepath, INSTALLDIR . '/file/' . $new_filename);
           
            // XXX: we should be using https where we can. TODO: detect whether the server
            // supports this.
            $photo_uri = 'http://' . common_config('site', 'server') . '/file/'
                . $new_filename;
            $thumb_uri = $photo_uri;

 
            $photo = Photo::saveNew($profile, $photo_uri, $thumb_uri, $title,
                $description, $options);
        
    }

    function showContent()
    {
        $form = new NewPhotoForm();
        $form->show();
    }
}



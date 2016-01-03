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

class EditphotoAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);
        $args = $this->returnToArgs();
        $this->user = common_current_user();
        $this->photoid = $args[1]['photoid'];
        $this->photo = GNUsocialPhoto::getKV('id', $this->photoid);
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
        if ($this->photo->title)
            return _m('Edit photo - ' . $this->photo->title);
        else
            return _m('Edit photo');
    }

    function showContent()
    {

        if ($this->photo->album_id == 0) {
            $this->element('p', array(), _('This photo does not exist or was deleted.'));
            return;
        }

        if ($this->user->profile_id != $this->photo->profile_id) {
            $this->element('p', array(), _('You are not authorized to edit this photo.'));
            return;
        } 

        //showForm() data
        if(!empty($this->msg)) {
            $class = ($this->success) ? 'success' : 'error';
            $this->element('p', array('class' => $class), $this->msg);
        }

        $this->element('img', array('src' => $this->photo->uri));
        $this->elementStart('form', array('method' => 'post',
                                          'action' => '/editphoto/' . $this->photo->id));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('phototitle', _("Title"), $this->photo->title, _("The title of the photo. (Optional)"));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->textarea('photo_description', _("Description"), $this->photo->photo_description, _("A description of the photo. (Optional)"));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->dropdown('album', _("Album"), $this->albumList(), _("The album in which to place this photo"), false, $this->photo->album_id);
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('update', _('Update'));
        $this->elementEnd('form');
        $this->element('br');            
        $this->elementStart('form', array('method' => 'post',
                                          'action' => '/editphoto/' . $this->photo->id));
        $this->element('input', array('type' => 'submit',
                                      'name' => 'delete',
                                      'value' => _('Delete Photo'),
                                      'id' => 'delete',
                                      'class' => 'submit',
                                      'onclick' => 'return confirm(\'Are you sure you would like to delete this photo?\')'));
        $this->elementEnd('form');

    }

    function handlePost()
    {

        common_log(LOG_INFO, 'handlPost()!');

        if ($this->photo->album_id == 0) {
            $this->element('p', array(), _('This photo does not exist or was deleted.'));
            return;
        }

        if ($this->user->profile_id != $this->photo->profile_id) {
            $this->element('p', array(), _('You are not authorized to edit this photo.'));
            return;
        } 

        if ($this->arg('update')) {
            $this->updatePhoto();
        }
        if ($this->arg('delete')) {
            $this->deletePhoto();
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

    function updatePhoto()
    {
        $cur = common_current_user();
        $this->photo->title = $this->trimmed('phototitle');
        $this->photo->photo_description = $this->trimmed('photo_description');

        $profile_id = $cur->id;
       
        $album = GNUsocialPhotoAlbum::getKV('album_id', $this->trimmed('album'));
        if ($album->profile_id != $profile_id) {
            $this->showForm(_('Error: This is not your album!'));
            return;
        }
        $this->photo->album_id = $album->album_id;
        if ($this->photo->validate())
            $this->photo->update();
        else {
            $this->showForm(_('Error: The photo data is not valid.'));
            return;
        }
        common_redirect('/photo/' . $this->photo->id, '303');
        // common_redirect exits
    }

    function deletePhoto()
    {
        //For redirection
        $oldalbum = $this->album_id;

        $notice = Notice::getKV('id', $this->photo->notice_id);

        $this->photo->delete();
        
        if (Event::handle('StartDeleteOwnNotice', array($this->user, $notice))) {
            $notice->deleteAs($this->scoped);
            Event::handle('EndDeleteOwnNotice', array($this->user, $notice));
        }
       $this->showForm(_('Success!'));
        common_redirect('/' . $this->user->nickname . '/photos/' . $oldalbum, '303');
    }

}

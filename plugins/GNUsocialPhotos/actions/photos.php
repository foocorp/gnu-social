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

require_once INSTALLDIR.'/lib/personalgroupnav.php';

class PhotosAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);

        $args = $this->returnToArgs();
        $username = $args[1]['nickname'];
        $this->albumid = $args[1]['albumid'];
        if (common_valid_profile_tag($username) == 0) {
            $this->user = null;
        } else {
            $this->user = Profile::getKV('nickname', $username);
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        if (empty($this->user)) {
            return _m('No such user.');
        } else {
            return sprintf(_m("%s's Photos."), $this->user->nickname);
        }
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showResizeImagesBox()
    {
        $this->elementStart('select', array('onchange' => 'return scalePhotosToSize(this.value)'));
        $this->element('option', array('value' => ''), "");
        $this->element('option', array('value' => '60'), _("Thumbnail"));
        $this->element('option', array('value' => '120'), _("Medium"));
        $this->element('option', array('value' => '400'), _("Normal"));
        $this->elementEnd('select');
    }        

    function showAlbums()
    {
        $album = new GNUsocialPhotoAlbum();
        $album->profile_id = $this->user->id;

        $albums = array();
        if (!$album->find()) {
            GNUsocialPhotoAlbum::newAlbum($this->user->id, 'Default');
        }

        $this->elementStart('div', array('class' => 'galleryheader'));
        //$this->element('a', array('href' => '#',
        //                          'onclick' => 'return increasePhotoSize()'), '+');
        //$this->raw(' | ');
        //$this->element('a', array('href' => '#',
        //                          'onclick' => 'return decreasePhotoSize()'), '-');

        $this->showResizeImagesBox();
        $this->elementEnd('div');



        while ($album->fetch()) {
            $this->elementStart('div', array('class' => 'photocontainer'));
            $this->elementStart('a', array('href' => $album->getPageLink()));
            $this->element('img', array('src' => $album->getThumbUri(),
                                        'class' => 'albumingallery'));
            $this->elementEnd('a');
            $this->element('h3', array(), $album->album_name);
            $this->elementEnd('div');
        }
        
    }
    
    function showAlbum($album_id)
    {
        $album = GNUSocialPhotoAlbum::getKV('album_id', $album_id);
        if (!$album) {
            return;
        }

        $page = $_GET['pageid'];
        if (!filter_var($page, FILTER_VALIDATE_INT)){
            $page = 1;
        }

        $photos = GNUsocialPhoto::getGalleryPage($page, $album->album_id, 9);
        $this->elementStart('div', array('class' => 'galleryheader'));
        if ($page > 1) { 
            $this->element('a', array('href' => $album->getPageLink() . '?pageid=' . ($page-1)), 'Previous page');
            $this->raw(' | ');
        }
        if (GNUsocialPhoto::getGalleryPage($page+1, $album->album_id, 9)) {
            $this->element('a', array('href' => $album->getPageLink() . '?pageid=' . ($page+1) ), 'Next page');
            $this->raw(' | ');
        }

        //$this->element('a', array('href' => '#',
        //                          'onclick' => 'return increasePhotoSize()'), '+');
        //$this->raw(' | ');
        //$this->element('a', array('href' => '#',
        //                          'onclick' => 'return decreasePhotoSize()'), '-');
        //$this->raw(' | ');

        $this->showResizeImagesBox();
        $this->elementEnd('div');

        foreach ($photos as $photo) {
            $this->elementStart('a', array('href' => $photo->getPageLink()));
            $this->elementStart('div', array('class' => 'photocontainer'));
            $this->element('img', array('src' => $photo->thumb_uri,
                                        'class' => 'photoingallery'));
            $this->element('div', array('class' => 'phototitle'), $photo->title);
            $this->elementEnd('div');
            $this->elementEnd('a');
        }

    }


    function showContent()
    {
        if (!empty($this->albumid))
            $this->showAlbum($this->albumid);
        else
            $this->showAlbums();
    }
}

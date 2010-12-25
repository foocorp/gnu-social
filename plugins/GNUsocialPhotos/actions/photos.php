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

class PhotosAction extends Action
{
    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);

        $args = $this->returnToArgs();
        $username = $args[1]['nickname'];
        if (common_valid_profile_tag($username) == 0) {
            $this->user = null;
        } else {
            $this->user = Profile::staticGet('nickname', $username);
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
        $nav = new GNUsocialPhotoNav($this);
        $nav->show();
    }

    function showContent()
    {
        if(empty($this->user)) {
            return;
        }
        $page = $_GET['pageid'];
        if (!filter_var($page, FILTER_VALIDATE_INT)){
            $page = 1;
        }
   
        if ($page > 1) { 
            $this->element('a', array('href' => 'photos?pageid=' . ($page-1)), 'Previous page');
        }
        $this->element('a', array('href' => 'photos?pageid=' . ($page+1) ), 'Next page');

        //TODO choice of available albums by user.
        //Currently based on fact that each user can only have one album.
        $album = GNUSocialPhotoAlbum::staticGet('profile_id', $this->user->id);
        $photos = GNUsocialPhoto::getGalleryPage($page, $album->album_id, 9);

        $this->elementStart('ul', array('class' => 'photothumbs'));
        foreach ($photos as $photo) {
            $this->elementStart('li');
			$photolink = '/' . $this->user->nickname . '/photo/' . $photo->notice_id;
            $this->elementStart('a', array('href' => $photolink));
            $this->element('img', array('src' => $photo->thumb_uri));
            $this->elementEnd('a');
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
    }
}

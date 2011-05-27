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
 * @category  Widget
 * @package   GNU Social
 * @author    Ian Denhardt <ian@zenhack.net>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PostvideoAction extends Action {
    var $user = null;
    var $url = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->user = common_current_user();

        if(empty($this->user)){
            throw new ClientException(_('Must be logged in to post a video'),
                403);
        }

        if($this->isPost()){
            $this->checkSessionToken();
        }

        $this->url = filter_var($this->trimmed('url'), FILTER_SANITIZE_URL);
        $this->url = filter_var($this->url, FILTER_VALIDATE_URL);

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
        if (empty($this->url)) {
            throw new ClientException(_('Bad URL.'));
        }

        $profile = $this->user->getProfile();

        $options = array();
        
        ToSelector::fillOptions($this, $options);

        $vid = Video::saveNew($profile, $this->url, $options);

        common_redirect($vid->uri, 303);
    }
 
    function showContent()
    {
        $form  = new VideoForm();
        $form->show();
    }
}

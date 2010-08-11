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
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PostvideoAction extends Action {
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
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost($args);
        }
        $this->showPage();
    }

    function handlePost($args)
    {
        if (!$this->arg('post')) {
            return;
        }
        if (empty($_POST['video_uri'])) {
            return;
        }
        $uri = $_POST['video_uri'];
        // XXX: validate your inputs, dummy.
        $rend = sprintf('<video src="%s", controls="controls">Sorry, your browser doesn\'t support the video tag.</video>', $uri);
        Notice::saveNew($this->user->id, 'video : ' . $uri, 'web', array('rendered' => $rend));
    }
 
    function showContent()
    {
        if(empty($this->user)) {
            $this->element('p', array(), 'You are not logged in.');
        } else {
            $this->elementStart('form', array('method' => 'post',
                                              'action' => common_local_url('postvideo')));
            $this->element('input', array('name' => 'video_uri',
                                          'type' => 'text',
                                          'id' => 'video_uri'));
            $this->submit('post', _('Post'));
            $this->elementEnd('form');
        }
    }
}

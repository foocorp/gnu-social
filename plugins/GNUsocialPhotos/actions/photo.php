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

if (!defined('GNUSOCIAL')) { exit(1); }

class PhotoAction extends ManagedAction
{
    var $user = null;
    var $conv = null;   // Conversation dataobject

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $args = $this->returnToArgs();
        $this->photoid = $args[1]['photoid'];
        $this->photo = GNUsocialPhoto::getKV('id', $this->photoid);
        $this->notice = Notice::getKV('id', $this->photo->notice_id);

        $this->user = Profile::getKV('id', $this->notice->profile_id);
        $this->conv = $this->notice->getConversation();

        return true;
    }

    function title()
    {
        if (empty($this->user)) {
            return _m('No such user.');
        } else if (empty($this->photo)) {
            return _m('No such photo.');
        } else if (!empty($this->photo->title)) {
            return $this->photo->title;
        } else {
            return sprintf(_m("%s's Photo."), $this->user->nickname);
        }
    }

    function showLocalNav()
    {
        $nav = new GNUsocialPhotoNav($this, $this->user->nickname);
        $nav->show();
    }

    function showContent()
    {
        if(empty($this->user)) {
            return;
        }

        $this->elementStart('a', array('href' => $this->photo->uri));
        $this->element('img', array('src' => $this->photo->uri));
        $this->elementEnd('a');

        //Image "toolbar"
        $cur = common_current_user();
        if($this->photo->profile_id == $cur->profile_id) {
            $this->elementStart('div', array('id' => 'image_toolbar'));
            $this->element('a', array('href' => '/editphoto/' . $this->photo->id), 'Edit');
            $this->elementEnd('div');
        }

        $this->element('p', array('class' => 'photodescription'), $this->photo->photo_description);
        //This is a hack to hide the top-level comment
        $this->element('style', array(), "#notice-{$this->photo->notice_id} div { display: none } #notice-{$this->photo->notice_id} ol li div { display: inline }");

        if (Event::handle('StartShowConversation', array($this, $this->conv, $this->scoped))) {
            $notices = $this->conv->getNotices();
            $nl = new FullThreadedNoticeList($notices, $this, $this->scoped);
            $cnt = $nl->show();
        }
        Event::handle('EndShowConversation', array($this, $this->conv, $this->scoped));
    }
}

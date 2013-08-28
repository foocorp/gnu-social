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

class GNUsocialPhotoPlugin extends MicroAppPlugin
{

    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('photo', Photo::schemaDef());

        return true;
    }

    function onRouterInitialized($m)
    {
        $m->connect('main/photo/new', array('action' => 'newphoto'));
        $m->connect('main/photo/:id', array('action' => 'showphoto'));
        return true;
    }

    function entryForm($out)
    {
        return new NewPhotoForm($out);
    }

    function appTitle()
    {
        return _('Photo');
    }

    function tag()
    {
        return 'Photo';
    }

    function types()
    {
        return array(Photo::OBJECT_TYPE);
    }

    function saveNoticeFromActivity($activity, $actor, $options=array())
    {

        if(count($activity->objects) != 1) {
            throw new Exception('Too many activity objects.');
        }

        $photoObj = $activity->objects[0];

        if ($photoObj->type != Photo::OBJECT_TYPE) {
            throw new Exception('Wrong type for object.');
        }

        $photo_uri = $photoObj->largerImage;
        $thumb_uri = $photo_uri;
        if(!empty($photoObj->thumbnail)){
            $thumb_uri = $photoObj->thumbnail;
        }

        $description = $photoObj->description;
        $title = $photoObj->title;
        
        $options['object_type'] = Photo::OBJECT_TYPE;

        Photo::saveNew($actor, $photo_uri, $thumb_uri, $title, $description, $options);
   
    }

    function activityObjectFromNotice($notice)
    {

        $photo = Photo::getByNotice($notice);
        
        $object = new ActivityObject();
        $object->id = $notice->uri;
        $object->type = Photo::OBJECT_TYPE;
        $object->title = $photo->title;
        $object->summary = $notice->content;
        $object->link = $notice->bestUrl();

        $object->largerImage = $photo->photo_uri;
        $object->thumbnail = $photo->thumb_uri;
        $object->description = $photo->description;
        
        return $object;
        
    }

    function showNotice($notice, $out)
    {
        $photo = Photo::getByNotice($notice);
        if ($photo) {
            if($photo->title){
                // TODO: ugly. feel like we should have a more abstract way
                // of choosing the h-level.
                $out->element('h3', array(), $title);
            }
            $out->element('img', array('src' => $photo->photo_uri,
                'width' => '100%'));
            // TODO: add description
        }
    }

    function deleteRelated($notice)
    {
        $photo = Photo::getByNotice($notice);
        if ($photo) {
            $photo->delete();
        }
    }
}

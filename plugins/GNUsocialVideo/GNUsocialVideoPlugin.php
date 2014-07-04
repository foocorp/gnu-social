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

class GNUsocialVideoPlugin extends MicroAppPlugin
{

    var $oldSaveNew = true;

    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('video', Video::schemaDef());

        return true;
    }

    function onRouterInitialized($m)
    {
        $m->connect('main/postvideo', array('action' => 'postvideo'));
        $m->connect('showvideo/:id', array('action' => 'showvideo'));
        return true;
    }

    function entryForm($out)
    {
        return new VideoForm($out);
    }

    function appTitle()
    {
        return _('Video');
    }

    function tag()
    {
        return 'GNUsocialVideo';
    }

    function types()
    {
        return array(Video::OBJECT_TYPE);
    }

    function saveNoticeFromActivity(Activity $activity, Profile $actor, array $options=array())
    {
        if(count($activity->objects) != 1) {
            throw new Exception('Too many activity objects.');
        }

        $videoObj = $activity->objects[0];

        if ($videoObj->type != Video::OBJECT_TYPE) {
            throw new Exception('Wrong type for object.');
        }

        // For now we read straight from the xml tree, no other way to get this information.
        // When there's a better API for this, we should change to it.
        $uri = ActivityUtils::getLink($activity->entry, 'enclosure');

        $options['object_type'] = Video::OBJECT_TYPE;

        Video::saveNew($actor, $uri, $options);
   
    }

    function activityObjectFromNotice(Notice $notice)
    {
        $object = new ActivityObject();
        $object->id = $notice->uri;
        $object->type = Video::OBJECT_TYPE;
        $object->title = $notice->content;
        $object->summary = $notice->content;
        $object->link = $notice->getUrl();

        $vid = Video::getByNotice($notice);

        if ($vid) {
            $object->extra[] = array('link', array('rel' => 'enclosure', 'href' => $vid->url), array());
        }
        
        return $object;
        
    }

    function showNotice(Notice $notice, HTMLOutputter $out)
    {
        $vid = Video::getByNotice($notice);
        if ($vid) {
            $out->element('video', array('src' => $vid->url,
                'width' => '100%',
                'controls' => 'controls'));
        }
    }

    function deleteRelated(Notice $notice)
    {
        $vid = Video::getByNotice($notice);
        if ($vid) {
            $vid->delete();
        }
    }
}

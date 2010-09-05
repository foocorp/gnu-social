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

/* Photo sharing plugin */

if (!defined('STATUSNET')) {
    exit(1);
}

class GNUsocialPhotosPlugin extends Plugin
{

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'PhotosAction':
            include_once $dir . '/lib/photolib.php';
            include_once $dir . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            break;
        case 'PhotouploadAction':
            include_once $dir . '/lib/photolib.php';
            include_once $dir . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            break;
        default:
            break;
        }

        include_once $dir . '/classes/gnusocialphoto.php';
        include_once $dir . '/classes/gnusocialphotoalbum.php';
        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('GNUsocialPhoto',
                                array(new ColumnDef('notice_id', 'int(11)', null, false),
                                      new ColumnDef('album_id', 'int(11)', null, false),
                                      new ColumnDef('uri', 'varchar(512)', null, false),
                                      new ColumnDef('thumb_uri', 'varchar(512)', null, false)));
        $schema->ensureTable('GNUsocialPhotoAlbum',
                                array(new ColumnDef('album_id', 'int(11)', null, false, 'PRI', null, null, true),
                                      new ColumnDef('profile_id', 'int(11)', null, false),
                                      new ColumnDef('album_name', 'varchar(256)', null, false)));
                                          
    }

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/photos', array('action' => 'photos'));
        $m->connect('main/uploadphoto', array('action' => 'photoupload'));
        common_log(LOG_INFO, "init'd!");
        return true;
    }

    function onStartActivityDefaultObjectType(&$notice, &$xs, &$type)
    {
        $photo = GNUsocialPhoto::staticGet('notice_id', $notice->id);
        if($photo) {
            $type = ActivityObject::PHOTO;
        }
    }

    function onStartActivityObjects(&$notice, &$xs, &$objects)
    {
        $photo = GNUsocialPhoto::staticGet('notice_id', $notice->id);
        if($photo) {
            $object = new ActivityObject();
            $object->thumbnail = $photo->thumb_uri;
            $object->largerImage = $photo->uri;
            $object->type = ActivityObject::PHOTO;
            
            $object->id = $notice->id;
            $objects[0] = $object;
        }
    }

    function onStartHandleFeedEntry($activity)
    {
        if ($activity->verb == ActivityVerb::POST) {
            $oprofile = Ostatus_profile::ensureActorProfile($activity);
            foreach ($activity->objects as $object) {
                if ($object->type == ActivityObject::PHOTO) {
                    $uri = $object->largerImage;
                    $thumb_uri = $object->thumbnail;
                    $profile_id = $oprofile->profile_id;
                    $source = 'unknown'; // TODO: put something better here.

                    $uri = filter_var($uri, FILTER_SANITIZE_URL);
                    $thumb_uri = filter_var($thumb_uri, FILTER_SANITIZE_URL);
                    $uri = filter_var($uri, FILTER_VALIDATE_URL);
                    $thumb_uri = filter_var($thumb_uri, FILTER_VALIDATE_URL);
                    if (!empty($uri) && !empty($thumb_uri)) {
                        GNUsocialPhoto::saveNew($profile_id, $thumb_uri, $uri, $source);
                    }
                    return false;
                }
            }
        }
        return true;
    }


    function onStartShowNoticeItem($action)
    {
        $photo = GNUsocialPhoto::staticGet('notice_id', $action->notice->id);
        if($photo) { 
            $action->out->elementStart('div', 'entry-title');
            $action->showAuthor();
            $action->out->elementStart('a', array('href' => $photo->uri));
            $action->out->element('img', array('src' => $photo->thumb_uri));
            $action->out->elementEnd('a');
            $action->out->elementEnd('div');
            $action->showNoticeInfo();
            $action->showNoticeOptions();
            return false;
        }
        return true;
    } 
}

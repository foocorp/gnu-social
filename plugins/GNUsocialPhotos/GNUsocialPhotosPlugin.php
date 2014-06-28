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
 * @author    Max Shinn    <trombonechamp@gmail.com>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

/* Photo sharing plugin */

if (!defined('STATUSNET')) {
    exit(1);
}

include_once $dir . '/lib/photolib.php';

class GNUsocialPhotosPlugin extends Plugin
{
    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('GNUsocialPhoto', GNUsocialPhoto::schemaDef());
        $schema->ensureTable('GNUsocialPhotoAlbum', GNUsocialPhotoAlbum::schemaDef());
    }

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/photos', array('action' => 'photos'));
        $m->connect(':nickname/photos/:albumid', array('action' => 'photos'));
        $m->connect('main/uploadphoto', array('action' => 'photoupload'));
        $m->connect('photo/:photoid', array('action' => 'photo'));
        $m->connect('editphoto/:photoid', array('action' => 'editphoto'));
        return true;
    }

    function onStartNoticeDistribute($notice)
    {
        common_log(LOG_INFO, "event: StartNoticeDistribute");
        if (GNUsocialPhotoTemp::$tmp) {
            GNUsocialPhotoTemp::$tmp->notice_id = $notice->id;
            $photo_id = GNUsocialPhotoTemp::$tmp->insert();
            if (!$photo_id) {
                common_log_db_error($photo, 'INSERT', __FILE__);
                throw new ServerException(_m('Problem saving photo.'));
            }
        }
        return true;
    }

    function onEndNoticeAsActivity($notice, &$activity)
    {
        common_log(LOG_INFO, 'photo plugin: EndNoticeAsActivity');
        $photo = GNUsocialPhoto::getKV('notice_id', $notice->id);
        if(!$photo) {
            common_log(LOG_INFO, 'not a photo.');
            return true;
        }

        $activity->objects[0]->type = ActivityObject::PHOTO;
        $activity->objects[0]->thumbnail = $photo->thumb_uri;
        $activity->objects[0]->largerImage = $photo->uri;
        return false;
    }


    function onStartHandleFeedEntry($activity)
    {
        common_log(LOG_INFO, 'photo plugin: onEndAtomPubNewActivity');
        $oprofile = Ostatus_profile::ensureActorProfile($activity);
        foreach ($activity->objects as $object) {
            if($object->type == ActivityObject::PHOTO) {
                $uri = $object->largerImage;
                $thumb_uri = $object->thumbnail;
                $profile_id = $oprofile->profile_id;
                $source = 'unknown'; // TODO: put something better here.

                common_log(LOG_INFO, 'uri : ' .  $uri);
                common_log(LOG_INFO, 'thumb_uri : ' . $thumb_uri);

                // It's possible this is validated elsewhere, but I'm not sure and
                // would rather be safe.
                $uri = filter_var($uri, FILTER_SANITIZE_URL);
                $thumb_uri = filter_var($thumb_uri, FILTER_SANITIZE_URL);
                $uri = filter_var($uri, FILTER_VALIDATE_URL);
                $thumb_uri = filter_var($thumb_uri, FILTER_VALIDATE_URL);

                if(empty($thumb_uri)) {
                    // We need a thumbnail, so if we aren't given one, use the actual picture for now.
                    $thumb_uri = $uri;
                }

                if (!empty($uri) && !empty($thumb_uri)) {
                    GNUsocialPhoto::saveNew($profile_id, $thumb_uri, $uri, $source, false);
                } else {
                    common_log(LOG_INFO, 'bad URI for photo');
                }
                return false;
            }
        }
        return true;
    }

    function onStartShowNoticeItem($action)
    {
        $photo = GNUsocialPhoto::getKV('notice_id', $action->notice->id);
        if($photo) { 
            $action->out->elementStart('div', 'entry-title');
            $action->showAuthor();
            $action->out->elementStart('a', array('href' => $photo->getPageLink()));
            $action->out->element('img', array('src' => $photo->thumb_uri,
                                    'width' => 256, 'height' => 192));
            $action->out->elementEnd('a');
            $action->out->elementEnd('div');
            $action->showNoticeInfo();
            $action->showNoticeOptions();
            return false;
        }
        return true;
    } 

    /*    function onEndShowNoticeFormData($action)
    {
        $link = "/main/uploadphoto";
        $action->out->element('label', array('for' => 'photofile'),_('Attach'));
        $action->out->element('input', array('id' => 'photofile',
                                     'type' => 'file',
                                     'name' => 'photofile',
                                     'title' => _('Upload a photo')));
    }
    */
    function onEndPersonalGroupNav(Menu $nav, Profile $target, Profile $scoped=null)
    {
      
        $nav->out->menuItem(common_local_url('photos',
                           array('nickname' => $nav->action->trimmed('nickname'))), _('Photos'), 
                           _('Photo gallery'), $nav->action->trimmed('action') == 'photos', 'nav_photos');
    }

    function onEndShowStyles($action)
    {
        $action->cssLink('/plugins/GNUsocialPhotos/res/style.css');
    }

    function onEndShowScripts($action)
    {
        $action->script('plugins/GNUsocialPhotos/res/gnusocialphotos.js');
    }
}


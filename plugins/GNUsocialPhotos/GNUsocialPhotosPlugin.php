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
        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('GNUsocialPhoto',
                                array(new ColumnDef('notice_id', 'integer', null, false, null, true, null, null, true),
                                      new ColumnDef('path', 'varchar(150)', null, false),
                                      new ColumnDef('thumb_path', 'varchar(156)', null, false), // 156 = 150 + strlen('thumb.')
                                      new ColumnDef('owner_id', 'int(11)', null, false)));
    }

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/photos', array('action' => 'photos'));
        $m->connect('main/uploadphoto', array('action' => 'photoupload'));
        common_log(LOG_INFO, "init'd!");
        return true;
    }

    /* function onStartActivityDefaultObjectType(&$notice, &$xs, &$type)
    {
        $photo = GNUsocialPhoto::staticGet('notice_id', $notice->id);
        if($photo) {
            $type = ActivityObject::PHOTO;
        }
    } */

    function onStartShowNoticeItem($action)
    {
        $photo = GNUsocialPhoto::staticGet('notice_id', $action->notice->id);
        if($photo) { 
            $action->out->elementStart('div', 'entry-title');
            $action->showAuthor();
            $action->out->elementStart('a', array('href' => 'http://' . common_config('site', 'server') . $photo->path));
            $action->out->element('img', array('src' => 'http://' . common_config('site', 'server') . $photo->thumb_path));
            $action->out->elementEnd('a');
            $action->out->elementEnd('div');
            $action->showNoticeInfo();
            $action->showNoticeOptions();
            return false;
        }
        return true;
    }
}

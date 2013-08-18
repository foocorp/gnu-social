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
 * @package   GNU Social
 * @author    Ian Denhardt <ian@zenhack.net>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if(!defined('STATUSNET')){
    exit(1);
}

/**
 * Data class for videos.
 */

class Video extends Managed_DataObject
{
    const OBJECT_TYPE = 'http://activitystrea.ms/schema/1.0/video';

    public $__table = 'video'; // table name
    public $id;                // char (36) // UUID
    public $uri;               // varchar (255)  // This is the corresponding notice's uri.
    public $url;               // varchar (255)
    public $profile_id;        // int

    public static function getByNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    public function getNotice()
    {
        return Notice::staticGet('uri', $this->uri);
    }

    public static function schemaDef()
    {
        return array(
            'description' => 'A video clip',
            'fields' => array(
                'id' => array('type' => 'char',
                              'length' => 36,
                              'not null' => true,
                              'description' => 'UUID'),
                'uri' => array('type' => 'varchar',
                               'length' => 255,
                               'not null' => true),
                'url' => array('type' => 'varchar',
                               'length' => 255,
                               'not null' => true),
                'profile_id' => array('type' => 'int', 'not null' => true),
            ),
            'primary key' => array('id'),
            'foreign keys' => array('video_profile_id__key' => array('profile' => array('profile_id' => 'id'))),
        );
    }

    function saveNew($profile, $url, $options=array())
    {
        $vid = new Video();

        $vid->id =  UUID::gen();
        $vid->profile_id = $profile->id;
        $vid->url = $url;


        $options['object_type'] = Video::OBJECT_TYPE;

        if (!array_key_exists('uri', $options)) { 
            $options['uri'] = common_local_url('showvideo', array('id' => $vid->id));
        }

        if (!array_key_exists('rendered', $options)) {
            $options['rendered'] = sprintf("<video src=\"%s\">Sorry, your browser doesn't support the video tag.</video>", $url);
        }

        $vid->uri = $options['uri'];
        
        $vid->insert();

        return Notice::saveNew($profile->id,
                               '',
                               'web',
                               $options);

    }
}

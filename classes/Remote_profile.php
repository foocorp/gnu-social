<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Table Definition for remote_profile
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Remote_profile extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'remote_profile';                  // table name
    public $id;                              // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key
    public $postnoticeurl;                   // varchar(255)
    public $updateprofileurl;                // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Remote_profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function hasRight($right)
    {
        $profile = Profile::staticGet($this->id);
        if ($profile) {
            return $profile->hasright($right);
        } else {
            // TRANS: Exception thrown when a right for a non-existing user profile is checked.
            throw new Exception(_("Missing profile."));
        }
    }

    public static function schemaDef()
    {
        return array(
            'description' => 'remote people (OMB)',
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
                'postnoticeurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for posting notices'),
                'updateprofileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for updates to this profile'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'remote_profile_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'remote_profile_id_fkey' => array('profile', array('id' => 'id')),
            ),
        );
    }
}

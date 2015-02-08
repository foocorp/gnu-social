<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class Ostatus_source extends Managed_DataObject
{
    public $__table = 'ostatus_source';

    public $notice_id; // notice we're referring to
    public $profile_uri; // uri of the ostatus_profile this came through -- may be a group feed
    public $method; // push or salmon
    public $created;
    public $modified;

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'Notice ID relation'),
                'profile_uri' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'Profile URI'),
                'method' => array('type' => 'enum("push","salmon")', 'not null' => true, 'description' => 'source method'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id'),
            'foreign keys' => array(
               'ostatus_source_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
               // not in profile table yet 'ostatus_source_profile_uri_fkey' => array('profile', array('profile_uri' => 'uri')),
            ),
            'indexes' => array(
                'ostatus_source_profile_uri_idx' => array('profile_uri'),
            ),
        );
   }

    /**
     * Save a remote notice source record; this helps indicate how trusted we are.
     * @param string $method
     */
    public static function saveNew(Notice $notice, Ostatus_profile $oprofile, $method)
    {
        $osource = new Ostatus_source();
        $osource->notice_id = $notice->id;
        $osource->profile_uri = $oprofile->uri;
        $osource->method = $method;
        $osource->created = common_sql_now();
        if ($osource->insert()) {
           return true;
        } else {
            common_log_db_error($osource, 'INSERT', __FILE__);
            return false;
        }
    }
}

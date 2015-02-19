<?php
/*
 * GNU social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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

class Attention extends Managed_DataObject
{
    public $__table = 'attention';  // table name
    public $notice_id;              // int(4) primary_key not_null
    public $profile_id;             // int(4) primary_key not_null
    public $reason;                 // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                // datetime()   not_null
    public $modified;               // timestamp   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'description' => 'Notice attentions to profiles (that are not a mention and not result of a subscription)',
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice_id to give attention'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'profile_id for feed receiver'),
                'reason' => array('type' => 'varchar', 'length' => 191, 'description' => 'Optional reason why this was brought to the attention of profile_id'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id', 'profile_id'),
            'foreign keys' => array(
                'attention_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
                'attention_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                'attention_notice_id_idx' => array('notice_id'),
                'attention_profile_id_idx' => array('profile_id'),
            ),
        );
    }

    public static function saveNew(Notice $notice, Profile $profile, $reason=null)
    {
        $att = new Attention();
        
        $att->notice_id = $notice->getID();
        $att->profile_id = $profile->getID();
        $att->reason = $reason;
        $att->created = common_sql_now();
        $result = $att->insert();

        if ($result === false) {
            throw new Exception('Could not saveNew in Attention');
        }
        return $att;
    }
}

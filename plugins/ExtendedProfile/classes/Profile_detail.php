<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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
 * DataObject class to store extended profile fields. Allows for storing
 * multiple values per a "field_name" (field_name property is not unique).
 *
 * Example:
 *
 *     Jed's Phone Numbers
 *     home  : 510-384-1992
 *     mobile: 510-719-1139
 *     work  : 415-231-1121
 *
 * We can store these phone numbers in a "field" represented by three
 * Profile_detail objects, each named 'phone_number' like this:
 *
 *     $phone1 = new Profile_detail();
 *     $phone1->field_name  = 'phone_number';
 *     $phone1->rel         = 'home';
 *     $phone1->field_value = '510-384-1992';
 *     $phone1->value_index = 1;
 *
 *     $phone1 = new Profile_detail();
 *     $phone1->field_name  = 'phone_number';
 *     $phone1->rel         = 'mobile';
 *     $phone1->field_value = '510-719-1139';
 *     $phone1->value_index = 2;
 *
 *     $phone1 = new Profile_detail();
 *     $phone1->field_name  = 'phone_number';
 *     $phone1->rel         = 'work';
 *     $phone1->field_value = '415-231-1121';
 *     $phone1->value_index = 3;
 *
 */
class Profile_detail extends Managed_DataObject
{
    public $__table = 'profile_detail';

    public $id;
    public $profile_id;  // profile this is for
    public $rel;         // detail for some field types; eg "home", "mobile", "work" for phones or "aim", "irc", "xmpp" for IM
    public $field_name;  // name
    public $field_value; // primary text value
    public $value_index; // relative ordering of multiple values in the same field
    public $date;        // related date
    public $ref_profile; // for people types, allows pointing to a known profile in the system
    public $created;
    public $modified;

    static function schemaDef()
    {
        return array(
            // No need for i18n. Table properties.
            'description'
                => 'Additional profile details for the ExtendedProfile plugin',
            'fields'      => array(
                'id'          => array('type' => 'serial', 'not null' => true),
                'profile_id'  => array('type' => 'int', 'not null' => true),
                'field_name'  => array(
                    'type'     => 'varchar',
                    'length'   => 16,
                    'not null' => true
                ),
                'value_index' => array('type' => 'int'),
                'field_value' => array('type' => 'text'),
                'date'        => array('type' => 'datetime'),
                'rel'         => array('type' => 'varchar', 'length' => 16),
                'rel_profile' => array('type' => 'int'),
                'created'     => array(
                    'type'     => 'datetime',
                    'not null' => true
                 ),
                'modified'    => array(
                    'type' => 'timestamp',
                    'not null' => true
                ),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'profile_detail_profile_id_field_name_value_index'
                    => array('profile_id', 'field_name', 'value_index'),
            )
        );
    }
}

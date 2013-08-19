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
 * @author    Max Shinn <trombonechamp@gmail.com>
 * @copyright 2010 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class GNUsocialProfileExtensionField extends Managed_DataObject
{
    public $__table = 'GNUsocialProfileExtensionField';
    public $id;          // int(11)
    public $systemname;  // varchar(64)
    public $title;       // varchar(255)
    public $description; // text
    public $type;        // varchar(255)
    public $created;     // datetime()   not_null
    public $modified;    // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'Unique ID for extension field'),
                'systemname' => array('type' => 'varchar', 'not null' => true, 'length' => 64, 'description' => 'field systemname'),
                'title' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'field title'),
                'description' => array('type' => 'text', 'not null' => true, 'description' => 'field description'),
                'type' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'field type'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'gnusocialprofileextensionfield_title_idx' => array('title'),
            ),
        );
    }

    function keyTypes()
    {
        return array('id' => 'K');
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    static function newField($title, $description=null, $type='str', $systemname=null)
    {
        $field = new GNUsocialProfileExtensionField();
        $field->title = $title;
        $field->description = $description;
        $field->type = $type;
        if (empty($systemname))
            $field->systemname = 'field' + $field->id;
        else
            $field->systemname = $systemname;
       
        $field->id = $field->insert();
        if (!$field->id){
            common_log_db_error($field, 'INSERT', __FILE__);
            throw new ServerException(_m('Error creating new field.'));
        }
        return $field;
    }

    static function allFields()
    {
        $field = new GNUsocialProfileExtensionField();
        $fields = array();
        if ($field->find()) {
            while($field->fetch()) {
                $fields[] = clone($field);
            }
        }
        return $fields;
    }
}

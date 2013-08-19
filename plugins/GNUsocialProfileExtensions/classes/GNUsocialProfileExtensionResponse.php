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

class GNUsocialProfileExtensionResponse extends Managed_DataObject
{
    public $__table = 'GNUsocialProfileExtensionResponse';
    public $id;           // int(11)
    public $extension_id; // int(11)
    public $profile_id;   // int(11)
    public $value;     // text
    public $created;      // datetime()   not_null
    public $modified;     // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'Unique ID for extension response'),
                'extension_id' => array('type' => 'int', 'not null' => true, 'description' => 'The extension field ID'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'Profile id that made the response'),
                'value' => array('type' => 'text', 'not null' => true, 'description' => 'response entry'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'foreign keys' => array(
                'gnusocialprofileextensionresponse_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                'gnusocialprofileextensionresponse_extension_id_fkey' => array('GNUsocialProfileExtensionField', array('extension_id' => 'id')),
            ),
            'indexes' => array(
                'gnusocialprofileextensionresponse_extension_id_idx' => array('extension_id'),
            ),
        );
    }

    static function newResponse($extension_id, $profile_id, $value)
    {

        $response = new GNUsocialProfileExtensionResponse();
        $response->extension_id = $extension_id;
        $response->profile_id = $profile_id;
        $response->value = $value;
       
        $response->id = $response->insert();
        if (!$response->id){
            common_log_db_error($response, 'INSERT', __FILE__);
            throw new ServerException(_m('Error creating new response.'));
        }
        return $response;
    }

    static function findResponsesByProfile($id)
    {
        $extf = 'GNUsocialProfileExtensionField';
        $extr = 'GNUsocialProfileExtensionResponse';
        $sql = "SELECT $extr.*, $extf.title, $extf.description, $extf.type, $extf.systemname FROM $extr JOIN $extf ON $extr.extension_id=$extf.id WHERE $extr.profile_id = $id";
        $response = new GNUsocialProfileExtensionResponse();
        $response->query($sql);
        $responses = array();

        while ($response->fetch()) {
            $responses[] = clone($response);
        }

        return $responses;
    }

}

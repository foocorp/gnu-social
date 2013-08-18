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

    function table()
    {
        return array('id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'extension_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'value' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL);
    }
    
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function keyTypes()
    {
        return array('id' => 'K');
    }

    function sequenceKey()
    {
        return array(false, false, false);
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

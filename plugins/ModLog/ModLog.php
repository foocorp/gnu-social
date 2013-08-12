<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * ModLog.php -- data object to store moderation logs
 * 
 * PHP version 5
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
 *
 * @category  Moderation
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class comment here
 *
 * @category Category here
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class ModLog extends Managed_DataObject
{
    public $__table = 'mod_log'; // table name

    public $id;           // UUID
    public $profile_id;   // profile id
    public $moderator_id; // profile id
    public $role;         // the role
    public $grant;        // 1 = grant, 0 = revoke
    public $created;      // datetime

    /**
     * Get an instance by key
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return TagSub object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Managed_DataObject::staticGet('ModLog', $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * @param array $kv array of key-value mappings
     *
     * @return TagSub object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Managed_DataObject::pkeyGet('ModLog', $kv);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array('description' => 'Log of moderation events',
                     'fields' => array(
                                       'id' => array('type' => 'varchar',
                                                     'length' => 36,
                                                     'not null' => true,
                                                     'description' => 'unique event ID'),
                                       'profile_id' => array('type' => 'int',
                                                             'not null' => true,
                                                             'description' => 'profile getting the role'),
                                       'moderator_id' => array('type' => 'int',
                                                               'description' => 'profile granting or revoking the role'),
                                       'role' => array('type' => 'varchar',
                                                       'length' => 32,
                                                       'not null' => true,
                                                       'description' => 'role granted or revoked'),
                                       'is_grant' => array('type' => 'int',
                                                           'size' => 'tiny',
                                                           'default' => 1,
                                                           'description' => 'Was this a grant or revocation of a role'),
                                       'created' => array('type' => 'datetime',
                                                          'not null' => true,
                                                          'description' => 'date this record was created')
                                       ),
                     'primary key' => array('id'),
                     'foreign keys' => array(
                                             'mod_log_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                                             'mod_log_moderator_id_fkey' => array('user', array('user_id' => 'id'))
                                             ),
                     'indexes' => array(
                                        'mod_log_profile_id_created_idx' => array('profile_id', 'created'),
                                        ),
                     );
    }
}

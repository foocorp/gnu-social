<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * User_openid_prefs.php
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
 * @category  OpenID
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
 * Store preferences for OpenID use in StatusNet
 *
 * @category OpenID
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class User_openid_prefs extends Managed_DataObject
{
    public $__table = 'user_openid_prefs'; // table name

    public $user_id;            // The User with the prefs
    public $hide_profile_link;  // Hide the link on the profile block?
    public $created;            // datetime
    public $modified;           // datetime

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return TagSub object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Managed_DataObject::staticGet('User_openid_prefs', $k, $v);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */

    public static function schemaDef()
    {
        return array(
                     'description' => 'Per-user preferences for OpenID display',
                     'fields' => array('user_id' => array('type' => 'integer',
                                                          'not null' => true,
                                                          'description' => 'User whose prefs we are saving'),
                                       'hide_profile_link' => array('type' => 'int',
                                                                    'not null' => true,
                                                                    'default' => 0,
                                                                    'description' => 'Whether to hide profile links from profile block'),
                                       'created' => array('type' => 'datetime',
                                                          'not null' => true,
                                                          'description' => 'date this record was created'),
                                       'modified' => array('type' => 'datetime',
                                                           'not null' => true,
                                                           'description' => 'date this record was modified'),
                                       ),
                     'primary key' => array('user_id'),
                     'foreign keys' => array('user_openid_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
                                             ),
                     'indexes' => array(),
                     );
    }
}

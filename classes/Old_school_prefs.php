<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Older-style UI preferences
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
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Separate table for storing UI preferences
 *
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Old_school_prefs extends Managed_DataObject
{
    public $__table = 'old_school_prefs';             // table name
    public $user_id;
    public $stream_mode_only;
    public $conversation_tree;
    public $stream_nicknames;
    public $created;
    public $modified;

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who has the preference'),
                'stream_mode_only' => array('type' => 'int', 
                                            'size' => 'tiny', 
                                            'default' => 1, 
                                            'description' => 'No conversation streams'),
                'conversation_tree' => array('type' => 'int', 
                                            'size' => 'tiny', 
                                            'default' => 1, 
                                            'description' => 'Hierarchical tree view for conversations'),
                'stream_nicknames' => array('type' => 'int', 
                                            'size' => 'tiny', 
                                            'default' => 1, 
                                            'description' => 'Show nicknames for authors and addressees in streams'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'old_school_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }
}

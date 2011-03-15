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

class Profile_detail extends Memcached_DataObject
{
    public $__table = 'submirror';

    public $id;

    public $profile_id;
    public $field;
    public $field_index; // relative ordering of multiple values in the same field

    public $value; // primary text value
    public $rel; // detail for some field types; eg "home", "mobile", "work" for phones or "aim", "irc", "xmpp" for IM
    public $ref_profile; // for people types, allows pointing to a known profile in the system

    public $created;
    public $modified;

    public /*static*/ function staticGet($k, $v=null)
    {
        return parent::staticGet(__CLASS__, $k, $v);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('id' =>  DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,

                     'profile_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'field' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'field_index' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,

                     'value' => DB_DATAOBJECT_STR,
                     'rel' => DB_DATAOBJECT_STR,
                     'ref_profile' => DB_DATAOBJECT_ID,

                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        // @fixme need a reverse key on (subscribed, subscriber) as well
        return array(new ColumnDef('id', 'integer',
                                   null, false, 'PRI'),

                     // @fixme need a unique index on these three
                     new ColumnDef('profile_id', 'integer',
                                   null, false),
                     new ColumnDef('field', 'varchar',
                                   16, false),
                     new ColumnDef('field_index', 'integer',
                                   null, false),

                     new ColumnDef('value', 'text',
                                   null, true),
                     new ColumnDef('rel', 'varchar',
                                   16, true),
                     new ColumnDef('ref_profile', 'integer',
                                   null, true),

                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    /**
     * Temporary hack to set up the compound index, since we can't do
     * it yet through regular Schema interface. (Coming for 1.0...)
     *
     * @param Schema $schema
     * @return void
     */
    static function fixIndexes($schema)
    {
        try {
            // @fixme this won't be a unique index... SIGH
            $schema->createIndex('profile_detail', array('profile_id', 'field', 'field_index'));
        } catch (Exception $e) {
            common_log(LOG_ERR, __METHOD__ . ': ' . $e->getMessage());
        }
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        // @fixme keys
        // need a sane key for reverse lookup too
        return array('id' => 'K');
    }

    function sequenceKey()
    {
        return array('id', true);
    }

}

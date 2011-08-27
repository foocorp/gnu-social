<?php
/**
 * Table Definition for user_openid
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class User_openid extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid';                     // table name
    public $canonical;                       // varchar(255)  primary_key not_null
    public $display;                         // varchar(255)  unique_key not_null
    public $user_id;                         // int(4)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('User_openid',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function table()
    {
        $db = $this->getDatabaseConnection();
        $dbtype = $db->phptype; // Database type is stored here. Crazy but true.

        return array('canonical' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'display'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'user_id'   => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified'  => ($dbtype == 'mysql' || $dbtype == 'mysqli') ?
                     DB_DATAOBJECT_MYSQLTIMESTAMP + DB_DATAOBJECT_NOTNULL :
                     DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
                     );
    }

    /**
     * List primary and unique keys in this table.
     * Unique keys used for lookup *MUST* be listed to ensure proper caching.
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function keyTypes()
    {
        return array('canonical' => 'K', 'display' => 'U', 'user_id' => 'U');
    }

    /**
     * No sequence keys in this table.
     */
    function sequenceKey()
    {
        return array(false, false, false);
    }

    static function hasOpenID($user_id)
    {
        $oid = new User_openid();

        $oid->user_id = $user_id;

        $cnt = $oid->find();

        return ($cnt > 0);
    }
}

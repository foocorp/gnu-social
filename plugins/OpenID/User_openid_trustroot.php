<?php
/**
 * Table Definition for user_openid_trustroot
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class User_openid_trustroot extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid_trustroot';                     // table name
    public $trustroot;                         // varchar(255) primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('User_openid_trustroot', $kv);
    }

    function table()
    {
        $db = $this->getDatabaseConnection();
        $dbtype = $db->phptype; // Database type is stored here. Crazy but true.

        return array('trustroot' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'user_id'   => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified'  => ($dbtype == 'mysql' || $dbtype == 'mysqli') ?
                     DB_DATAOBJECT_MYSQLTIMESTAMP + DB_DATAOBJECT_NOTNULL :
                     DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
                     );
    }

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function keyTypes()
    {
        return array('trustroot' => 'K', 'user_id' => 'K');
    }
}

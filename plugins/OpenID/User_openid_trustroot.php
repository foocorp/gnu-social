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

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'trustroot' => array('type' => 'varchar', 'not null' => true, 'length' => 255, 'description' => 'OpenID trustroot string'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'User ID for OpenID trustroot owner'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('trustroot', 'user_id'),
        );
    }
}

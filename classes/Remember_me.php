<?php
/**
 * Table Definition for remember_me
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Remember_me extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'remember_me';                     // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    {
        return Memcached_DataObject::staticGet('Remember_me',$k,$v);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who is logged in'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('code'),
            'foreign keys' => array(
                'remember_me_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }    
}

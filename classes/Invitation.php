<?php
/**
 * Table Definition for invitation
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Invitation extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'invitation';                      // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $address;                         // varchar(255)  multiple_key not_null
    public $address_type;                    // varchar(8)  multiple_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Invitation',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}

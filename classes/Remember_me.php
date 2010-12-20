<?php
/**
 * Table Definition for remember_me
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Remember_me extends Memcached_DataObject
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

    function sequenceKey()
    {
        return array(false, false);
    }
}

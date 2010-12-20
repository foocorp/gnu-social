<?php
/**
 * Table Definition for foreign_service
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_service extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_service';                 // table name
    public $id;                              // int(4)  primary_key not_null
    public $name;                            // varchar(32)  unique_key not_null
    public $description;                     // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Foreign_service',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}

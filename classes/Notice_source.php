<?php
/**
 * Table Definition for notice_source
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Notice_source extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_source';                   // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $name;                            // varchar(255)   not_null
    public $url;                             // varchar(255)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Notice_source',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}

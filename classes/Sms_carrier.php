<?php
/**
 * Table Definition for sms_carrier
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Sms_carrier extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'sms_carrier';                     // table name
    public $id;                              // int(4)  primary_key not_null
    public $name;                            // varchar(64)  unique_key
    public $email_pattern;                   // varchar(255)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Sms_carrier',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function toEmailAddress($sms)
    {
        return sprintf($this->email_pattern, $sms);
    }
}

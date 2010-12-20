<?php
/**
 * Table Definition for foreign_subscription
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_subscription extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_subscription';            // table name
    public $service;                         // int(4)  primary_key not_null
    public $subscriber;                      // int(4)  primary_key not_null
    public $subscribed;                      // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Foreign_subscription',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}

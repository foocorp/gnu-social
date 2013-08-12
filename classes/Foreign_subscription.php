<?php
/**
 * Table Definition for foreign_subscription
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_subscription extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_subscription';            // table name
    public $service;                         // int(4)  primary_key not_null
    public $subscriber;                      // int(4)  primary_key not_null
    public $subscribed;                      // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(

            'fields' => array(
                'service' => array('type' => 'int', 'not null' => true, 'description' => 'service where relationship happens'),
                'subscriber' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'subscriber on foreign service'),
                'subscribed' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'subscribed user'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('service', 'subscriber', 'subscribed'),
            'foreign keys' => array(
                'foreign_subscription_service_fkey' => array('foreign_service', array('service' => 'id')),
                'foreign_subscription_subscriber_fkey' => array('foreign_user', array('subscriber' => 'id', 'service' => 'service')),
                'foreign_subscription_subscribed_fkey' => array('foreign_user', array('subscribed' => 'id', 'service' => 'service')),
            ),
            'indexes' => array(
                'foreign_subscription_subscriber_idx' => array('service', 'subscriber'),
                'foreign_subscription_subscribed_idx' => array('service', 'subscribed'),
            ),
        );
    }
}

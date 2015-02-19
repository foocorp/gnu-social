<?php
/**
 * Table Definition for token
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Token extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'token';                           // table name
    public $consumer_key;                    // varchar(191)  primary_key not_null   not 255 because utf8mb4 takes more space
    public $tok;                             // char(32)  primary_key not_null
    public $secret;                          // char(32)   not_null
    public $type;                            // tinyint(1)   not_null
    public $state;                           // tinyint(1)
    public $verifier;                        // varchar(191)   not 255 because utf8mb4 takes more space
    public $verified_callback;               // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
    public static function schemaDef()
    {
        return array(
            'description' => 'OAuth token record',
            'fields' => array(
                'consumer_key' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'unique identifier, root URL'),
                'tok' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'identifying value'),
                'secret' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'secret value'),
                'type' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 0, 'description' => 'request or access'),
                'state' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'for requests, 0 = initial, 1 = authorized, 2 = used'),
                'verifier' => array('type' => 'varchar', 'length' => 191, 'description' => 'verifier string for OAuth 1.0a'),
                'verified_callback' => array('type' => 'varchar', 'length' => 191, 'description' => 'verified callback URL for OAuth 1.0a'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('consumer_key', 'tok'),
            'foreign keys' => array(
                'token_consumer_key_fkey' => array('consumer', array('consumer_key'=> 'consumer_key')),
            ),
        );
    }
}

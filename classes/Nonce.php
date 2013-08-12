<?php
/**
 * Table Definition for nonce
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Nonce extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'nonce';                           // table name
    public $consumer_key;                    // varchar(255)  primary_key not_null
    public $tok;                             // char(32)
    public $nonce;                           // char(32)  primary_key not_null
    public $ts;                              // datetime()  primary_key not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Nonce',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * Compatibility hack for PHP 5.3
     *
     * The statusnet.links.ini entry cannot be read because "," is no longer
     * allowed in key names when read by parse_ini_file().
     *
     * @return   array
     * @access   public
     */
    function links()
    {
        return array('consumer_key,token' => 'token:consumer_key,token');
    }

    public static function schemaDef()
    {
        return array(
            'description' => 'OAuth nonce record',
            'fields' => array(
                'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
                'tok' => array('type' => 'char', 'length' => 32, 'description' => 'buggy old value, ignored'),
                'nonce' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'nonce'),
                'ts' => array('type' => 'datetime', 'not null' => true, 'description' => 'timestamp sent'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('consumer_key', 'ts', 'nonce'),
        );
    }
}

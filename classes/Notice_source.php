<?php
/**
 * Table Definition for notice_source
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Notice_source extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_source';                   // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $name;                            // varchar(255)   not_null
    public $url;                             // varchar(255)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'source code'),
                'name' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'name of the source'),
                'url' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'url to link to'),
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'date this record was created'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('code'),
        );
    }
}

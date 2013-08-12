<?php
/**
 * Table Definition for schema_version
 */

class Schema_version extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'schema_version';      // table name
    public $table_name;                      // varchar(64)  primary_key not_null
    public $checksum;                        // varchar(64)  not_null
    public $modified;                        // datetime()   not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'To avoid checking database structure all the time, we store a checksum of the expected schema info for each table here. If it has not changed since the last time we checked the table, we can leave it as is.',
            'fields' => array(
                'table_name' => array('type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Table name'),
                'checksum' => array('type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Checksum of schema array; a mismatch indicates we should check the table more thoroughly.'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('table_name'),
        );
    }
}

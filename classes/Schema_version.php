<?php
/**
 * Table Definition for schema_version
 */

class Schema_version extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'schema_version';      // table name
    public $table_name;                      // varchar(64)  primary_key not_null
    public $checksum;                        // varchar(64)  not_null
    public $modified;                        // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Schema_version',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}

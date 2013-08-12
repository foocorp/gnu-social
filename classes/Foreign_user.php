<?php
/**
 * Table Definition for foreign_user
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_user extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_user';                    // table name
    public $id;                              // bigint(8)  primary_key not_null
    public $service;                         // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key not_null
    public $nickname;                        // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'unique numeric key on foreign service'),
                'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'identifying URI'),
                'nickname' => array('type' => 'varchar', 'length' => 255, 'description' => 'nickname on foreign service'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id', 'service'),
            'foreign keys' => array(
                'foreign_user_service_fkey' => array('foreign_service', array('service' => 'id')),
            ),
            'unique keys' => array(
                'foreign_user_uri_key' => array('uri'),
            ),
        );
    }

    static function getForeignUser($id, $service) {

        $fuser = new Foreign_user();

        $fuser->id      = $id;
        $fuser->service = $service;

        $fuser->limit(1);

        $result = $fuser->find(true);

        return empty($result) ? null : $fuser;
    }

    static function getByNickname($nickname, $service)
    {
        if (empty($nickname) || empty($service)) {
            return null;
        } else {
            $fuser = new Foreign_user();
	    $fuser->service = $service;
	    $fuser->nickname = $nickname;
            $fuser->limit(1);

            $result = $fuser->find(true);

            return empty($result) ? null : $fuser;
        }
    }

    function updateKeys(&$orig)
    {
        $this->_connect();
        $parts = array();
        foreach (array('id', 'service', 'uri', 'nickname') as $k) {
            if (strcmp($this->$k, $orig->$k) != 0) {
                $parts[] = $k . ' = ' . $this->_quote($this->$k);
            }
        }
        if (count($parts) == 0) {
            // No changes
            return true;
        }
        $toupdate = implode(', ', $parts);

        $table = $this->tableName();
        if(common_config('db','quote_identifiers')) {
            $table = '"' . $table . '"';
        }
        $qry = 'UPDATE ' . $table . ' SET ' . $toupdate .
          ' WHERE id = ' . $this->id;
        $orig->decache();
        $result = $this->query($qry);
        if ($result) {
            $this->encache();
        }
        return $result;
    }
}

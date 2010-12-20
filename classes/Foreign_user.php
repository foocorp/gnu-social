<?php
/**
 * Table Definition for foreign_user
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_user extends Memcached_DataObject
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

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Foreign_user',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    // XXX:  This only returns a 1->1 single obj mapping.  Change?  Or make
    // a getForeignUsers() that returns more than one? --Zach
    static function getForeignUser($id, $service) {
        $fuser = new Foreign_user();
        $fuser->whereAdd("service = $service");
        $fuser->whereAdd("id = $id");
        $fuser->limit(1);

        if ($fuser->find()) {
            $fuser->fetch();
            return $fuser;
        }

        return null;
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
            # No changes
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

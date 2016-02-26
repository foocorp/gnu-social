<?php
/**
 * Table Definition for user_openid
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class User_openid extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid';                     // table name
    public $canonical;                       // varchar(191)  primary_key not_null
    public $display;                         // varchar(191)  unique_key not_null
    public $user_id;                         // int(4)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'canonical' => array('type' => 'varchar', 'not null' => true, 'length' => 191, 'description' => 'OpenID canonical string'),
                'display' => array('type' => 'varchar', 'not null' => true, 'length' => 191, 'description' => 'OpenID display string'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'User ID for OpenID owner'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('canonical'),
            'unique keys' => array(
                'user_openid_display_key' => array('display'),
            ),
            'indexes' => array(
                'user_openid_user_id_idx' => array('user_id'),
            ),
            'foreign keys' => array(
                'user_openid_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    public function getID()
    {
        if (!isset($this->user_id)) {
            throw new Exception('No ID set.');
        } elseif (empty($this->user_id)) {
            throw new Exception('Empty ID for object! (not inserted yet?).');
        }
        return intval($this->user_id);
    }

    static function hasOpenID($user_id)
    {
        $oid = new User_openid();

        $oid->user_id = $user_id;

        $cnt = $oid->find();

        return ($cnt > 0);
    }
}

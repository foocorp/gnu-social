<?php
/**
 * Table Definition for user_username
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class User_username extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_username';                     // table name
    public $user_id;                        // int(4)  not_null
    public $provider_name;                  // varchar(255)  primary_key not_null
    public $username;                       // varchar(255)  primary_key not_null
    public $created;                        // datetime()   not_null
    public $modified;                       // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'provider_name' => array('type' => 'varchar', 'length' => 255, 'description' => 'provider name'),
                'username' => array('type' => 'varchar', 'length' => 255, 'description' => 'username'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice id this title relates to'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('provider_name', 'username'),
            'foreign keys' => array(
                'user_username_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    /**
    * Register a user with a username on a given provider
    * @param User User object
    * @param string username on the given provider
    * @param provider_name string name of the provider
    * @return mixed User_username instance if the registration succeeded, false if it did not
    */
    static function register($user, $username, $provider_name)
    {
        $user_username = new User_username();
        $user_username->user_id = $user->id;
        $user_username->provider_name = $provider_name;
        $user_username->username = $username;
        $user_username->created = DB_DataObject_Cast::dateTime();

        if($user_username->insert()){
            return $user_username;
        }else{
            return false;
        }
    }
}

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

    /* Static get */
    function staticGet($k,$v=null)
    {
        return Memcached_DataObject::staticGet('User_username',$k,$v);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

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

    function table() {
        return array(
            'user_id'     => DB_DATAOBJECT_INT,
            'username'   => DB_DATAOBJECT_STR,
            'provider_name'   => DB_DATAOBJECT_STR ,
            'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
        );
    }

    // now define the keys.
    function keys() {
        return array('provider_name' => 'K', 'username' => 'K');
    }
}

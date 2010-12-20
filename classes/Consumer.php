<?php
/**
 * Table Definition for consumer
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Consumer extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'consumer';                        // table name
    public $consumer_key;                    // varchar(255)  primary_key not_null
    public $consumer_secret;                 // varchar(255)   not_null
    public $seed;                            // char(32)   not_null
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Consumer',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function generateNew()
    {
        $cons = new Consumer();
        $rand = common_good_rand(16);

        $cons->seed            = $rand;
        $cons->consumer_key    = md5(time() + $rand);
        $cons->consumer_secret = md5(md5(time() + time() + $rand));
        $cons->created         = common_sql_now();

        return $cons;
    }

    /**
     * Delete a Consumer and related tokens and nonces
     *
     * XXX: Should this happen in an OAuthDataStore instead?
     *
     */
    function delete()
    {
        // XXX: Is there any reason NOT to do this kind of cleanup?

        $this->_deleteTokens();
        $this->_deleteNonces();

        parent::delete();
    }

    function _deleteTokens()
    {
        $token = new Token();
        $token->consumer_key = $this->consumer_key;
        $token->delete();
    }

    function _deleteNonces()
    {
        $nonce = new Nonce();
        $nonce->consumer_key = $this->consumer_key;
        $nonce->delete();
    }
}

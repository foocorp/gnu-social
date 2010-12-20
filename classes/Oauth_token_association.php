<?php
/**
 * Table Definition for oauth_association
 */
require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class Oauth_token_association extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_token_association';          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $application_id;                  // int(4)  primary_key not_null
    public $token;                           // varchar(255) primary key not null
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k, $v = NULL) {
        return Memcached_DataObject::staticGet('oauth_token_association', $k, $v);
    }
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getByUserAndToken($user, $token)
    {
        if (empty($user) || empty($token)) {
            return null;
        }

        $oau = new oauth_request_token();

        $oau->profile_id = $user->id;
        $oau->token      = $token;
        $oau->limit(1);

        $result = $oau->find(true);

        return empty($result) ? null : $oau;
    }
}

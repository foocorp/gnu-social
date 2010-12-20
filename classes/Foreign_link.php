<?php
/**
 * Table Definition for foreign_link
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_link extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_link';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $foreign_id;                      // bigint(8)  primary_key not_null unsigned
    public $service;                         // int(4)  primary_key not_null
    public $credentials;                     // varchar(255)
    public $noticesync;                      // tinyint(1)   not_null default_1
    public $friendsync;                      // tinyint(1)   not_null default_2
    public $profilesync;                     // tinyint(1)   not_null default_1
    public $last_noticesync;                 // datetime()
    public $last_friendsync;                 // datetime()
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Foreign_link',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getByUserID($user_id, $service)
    {
        if (empty($user_id) || empty($service)) {
            return null;
        }

        $flink = new Foreign_link();

        $flink->service = $service;
        $flink->user_id = $user_id;
        $flink->limit(1);

        $result = $flink->find(true);

        return empty($result) ? null : $flink;
    }

    static function getByForeignID($foreign_id, $service)
    {
        if (empty($foreign_id) || empty($service)) {
            return null;
        } else {
            $flink = new Foreign_link();
            $flink->service = $service;
            $flink->foreign_id = $foreign_id;
            $flink->limit(1);

            $result = $flink->find(true);

            return empty($result) ? null : $flink;
        }
    }

    function set_flags($noticesend, $noticerecv, $replysync, $friendsync)
    {
        if ($noticesend) {
            $this->noticesync |= FOREIGN_NOTICE_SEND;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_SEND;
        }

        if ($noticerecv) {
            $this->noticesync |= FOREIGN_NOTICE_RECV;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_RECV;
        }

        if ($replysync) {
            $this->noticesync |= FOREIGN_NOTICE_SEND_REPLY;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_SEND_REPLY;
        }

        if ($friendsync) {
            $this->friendsync |= FOREIGN_FRIEND_RECV;
        } else {
            $this->friendsync &= ~FOREIGN_FRIEND_RECV;
        }

        $this->profilesync = 0;
    }

    # Convenience methods
    function getForeignUser()
    {
        $fuser = new Foreign_user();
        $fuser->service = $this->service;
        $fuser->id = $this->foreign_id;

        $fuser->limit(1);

        if ($fuser->find(true)) {
            return $fuser;
        }

        return null;
    }

    function getUser()
    {
        return User::staticGet($this->user_id);
    }

    // Make sure we only ever delete one record at a time
    function safeDelete()
    {
        if (!empty($this->user_id)
            && !empty($this->foreign_id)
            && !empty($this->service))
        {
            return $this->delete();
        } else {
            common_debug(LOG_WARNING,
                'Foreign_link::safeDelete() tried to delete a '
                . 'Foreign_link without a fully specified compound key: '
                . var_export($this, true));
            return false;
        }
    }
}

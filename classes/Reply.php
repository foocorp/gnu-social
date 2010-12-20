<?php
/**
 * Table Definition for reply
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Reply extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'reply';                           // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $replied_id;                      // int(4)

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Reply',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * Wrapper for record insertion to update related caches
     */
    function insert()
    {
        $result = parent::insert();

        if ($result) {
            self::blow('reply:stream:%d', $this->profile_id);
        }

        return $result;
    }

    function stream($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        $ids = Notice::stream(array('Reply', '_streamDirect'),
                              array($user_id),
                              'reply:stream:' . $user_id,
                              $offset, $limit, $since_id, $max_id);
        return $ids;
    }

    function _streamDirect($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        $reply = new Reply();
        $reply->profile_id = $user_id;

        Notice::addWhereSinceId($reply, $since_id, 'notice_id', 'modified');
        Notice::addWhereMaxId($reply, $max_id, 'notice_id', 'modified');

        $reply->orderBy('modified DESC, notice_id DESC');

        if (!is_null($offset)) {
            $reply->limit($offset, $limit);
        }

        $ids = array();

        if ($reply->find()) {
            while ($reply->fetch()) {
                $ids[] = $reply->notice_id;
            }
        }

        return $ids;
    }
}

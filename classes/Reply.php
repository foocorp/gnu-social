<?php
/**
 * Table Definition for reply
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Reply extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'reply';                           // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $replied_id;                      // int(4)

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the reply'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'profile replied to'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
                'replied_id' => array('type' => 'int', 'description' => 'notice replied to (not used, see notice.reply_to)'),
            ),
            'primary key' => array('notice_id', 'profile_id'),
            'foreign keys' => array(
                'reply_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
                'reply_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                'reply_notice_id_idx' => array('notice_id'),
                'reply_profile_id_idx' => array('profile_id'),
                'reply_replied_id_idx' => array('replied_id'),
                'reply_profile_id_modified_notice_id_idx' => array('profile_id', 'modified', 'notice_id')
            ),
        );
    }    
	
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

    static function stream($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        $stream = new ReplyNoticeStream($user_id);
        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }
}

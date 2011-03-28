<?php
/**
 * Table Definition for request_queue
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Group_join_queue extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_join_queue';       // table name
    public $profile_id;
    public $group_id;
    public $created;

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Group_join_queue',$k,$v); }

    /* Pkey get */
    function pkeyGet($k)
    { return Memcached_DataObject::pkeyGet('Group_join_queue',$k); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Holder for group join requests awaiting moderation.',
            'fields' => array(
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile making the request'),
                'group_id' => array('type' => 'int', 'description' => 'remote or local group to join, if any'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('profile_id', 'group_id'),
            'indexes' => array(
                'group_join_queue_profile_id_created_idx' => array('profile_id', 'created'),
                'group_join_queue_group_id_created_idx' => array('group_id', 'created'),
            ),
            'foreign keys' => array(
                'group_join_queue_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                'group_join_queue_group_id_fkey' => array('user_group', array('group_id' => 'id')),
            )
        );
    }

    public static function saveNew(Profile $profile, User_group $group)
    {
        $rq = new Group_join_queue();
        $rq->profile_id = $profile->id;
        $rq->group_id = $group->id;
        $rq->created = common_sql_now();
        $rq->insert();
        return $rq;
    }

    /**
     * Send notifications via email etc to group administrators about
     * this exciting new pending moderation queue item!
     */
    public function notify()
    {
        $joiner = Profile::staticGet('id', $this->profile_id);
        $group = User_group::staticGet('id', $this->group_id);
        mail_notify_group_join_pending($group, $joiner);
    }
}

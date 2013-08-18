<?php

/**
 * Table Definition for group_inbox
 */
class Group_inbox extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_inbox';                     // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Many-many table listing notices posted to a given group, or which groups a given notice was posted to.',
            'fields' => array(
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group receiving the message'),
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
            ),
            'primary key' => array('group_id', 'notice_id'),
            'foreign keys' => array(
                'group_inbox_group_id_fkey' => array('user_group', array('group_id' => 'id')),
                'group_inbox_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'group_inbox_created_idx' => array('created'),
                'group_inbox_notice_id_idx' => array('notice_id'),
                'group_inbox_group_id_created_notice_id_idx' => array('group_id', 'created', 'notice_id'),
            ),
        );
    }
}

<?php
/**
 * Table Definition for local_group
 */

class Local_group extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'local_group';                     // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Record for a user group on the local site, with some additional info not in user_group',
            'fields' => array(
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group represented'),
                'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'group represented'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('group_id'),
            'foreign keys' => array(
                'local_group_group_id_fkey' => array('user_group', array('group_id' => 'id')),
            ),
            'unique keys' => array(
                'local_group_nickname_key' => array('nickname'),
            ),
        );
    }

    public function getProfile()
    {
        $group = User_group::getKV('id', $this->group_id);
        if (!($group instanceof User_group)) {
            return null;    // TODO: Throw exception when other code is ready
        }
        return $group->getProfile();
    }

    function setNickname($nickname)
    {
        $this->decache();
        $qry = 'UPDATE local_group set nickname = "'.$this->escape($nickname).'" where group_id = ' . $this->group_id;

        $result = $this->query($qry);

        if ($result) {
            $this->nickname = $nickname;
            $this->fixupTimestamps();
            $this->encache();
        } else {
            common_log_db_error($local, 'UPDATE', __FILE__);
            // TRANS: Server exception thrown when updating a local group fails.
            throw new ServerException(_('Could not update local group.'));
        }

        return $result;
    }
}

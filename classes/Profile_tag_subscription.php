<?php
/**
 * Table Definition for profile_tag_subscription
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_tag_subscription extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag_subscription';                     // table name
    public $profile_tag_id;                         // int(4)  not_null
    public $profile_id;                             // int(4)  not_null
    public $created;                                // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                               // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'profile_tag_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile_tag'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('profile_tag_id', 'profile_id'),
            'foreign keys' => array(
                'profile_tag_subscription_profile_list_id_fkey' => array('profile_list', array('profile_tag_id' => 'id')),
                'profile_tag_subscription_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                // @fixme probably we want a (profile_id, created) index here?
                'profile_tag_subscription_profile_id_idx' => array('profile_id'),
                'profile_tag_subscription_created_idx' => array('created'),
            ),
        );
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_tag_subscription', $kv);
    }

    static function add($peopletag, $profile)
    {
        if ($peopletag->private) {
            return false;
        }

        if (Event::handle('StartSubscribePeopletag', array($peopletag, $profile))) {
            $args = array('profile_tag_id' => $peopletag->id,
                          'profile_id' => $profile->id);
            $existing = Profile_tag_subscription::pkeyGet($args);
            if(!empty($existing)) {
                return $existing;
            }

            $sub = new Profile_tag_subscription();
            $sub->profile_tag_id = $peopletag->id;
            $sub->profile_id = $profile->id;
            $sub->created = common_sql_now();

            $result = $sub->insert();

            if (!$result) {
                common_log_db_error($sub, 'INSERT', __FILE__);
                // TRANS: Exception thrown when inserting a list subscription in the database fails.
                throw new Exception(_('Adding list subscription failed.'));
            }

            $ptag = Profile_list::getKV('id', $peopletag->id);
            $ptag->subscriberCount(true);

            Event::handle('EndSubscribePeopletag', array($peopletag, $profile));
            return $ptag;
        }
    }

    static function remove($peopletag, $profile)
    {
        $sub = Profile_tag_subscription::pkeyGet(array('profile_tag_id' => $peopletag->id,
                                              'profile_id' => $profile->id));

        if (empty($sub)) {
            // silence is golden?
            return true;
        }

        if (Event::handle('StartUnsubscribePeopletag', array($peopletag, $profile))) {
            $result = $sub->delete();

            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                // TRANS: Exception thrown when deleting a list subscription from the database fails.
                throw new Exception(_('Removing list subscription failed.'));
            }

            $peopletag->subscriberCount(true);

            Event::handle('EndUnsubscribePeopletag', array($peopletag, $profile));
            return true;
        }
    }

    // called if a tag gets deleted / made private
    static function cleanup($profile_list) {
        $subs = new self();
        $subs->profile_tag_id = $profile_list->id;
        $subs->find();

        while($subs->fetch()) {
            $profile = Profile::getKV('id', $subs->profile_id);
            Event::handle('StartUnsubscribePeopletag', array($profile_list, $profile));
            // Delete anyway
            $subs->delete();
            Event::handle('StartUnsubscribePeopletag', array($profile_list, $profile));
        }
    }

    function insert()
    {
        $result = parent::insert();
        if ($result) {
            self::blow('profile_list:subscriber_count:%d', 
                       $this->profile_tag_id);
        }
        return $result;
    }

    function delete()
    {
        $result = parent::delete();
        if ($result) {
            self::blow('profile_list:subscriber_count:%d', 
                       $this->profile_tag_id);
        }
        return $result;
    }
}

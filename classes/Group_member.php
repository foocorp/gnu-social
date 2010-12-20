<?php
/**
 * Table Definition for group_member
 */

class Group_member extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_member';                    // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $is_admin;                        // tinyint(1)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Group_member',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Group_member', $kv);
    }

    /**
     * Method to add a user to a group.
     *
     * @param integer $group_id   Group to add to
     * @param integer $profile_id Profile being added
     * 
     * @return Group_member new membership object
     */

    static function join($group_id, $profile_id)
    {
        $member = new Group_member();

        $member->group_id   = $group_id;
        $member->profile_id = $profile_id;
        $member->created    = common_sql_now();

        $result = $member->insert();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when joining a group fails.
            throw new Exception(_("Group join failed."));
        }

        return $member;
    }

    static function leave($group_id, $profile_id)
    {
        $member = Group_member::pkeyGet(array('group_id' => $group_id,
                                              'profile_id' => $profile_id));

        if (empty($member)) {
            // TRANS: Exception thrown when trying to leave a group the user is not a member of.
            throw new Exception(_("Not part of group."));
        }

        $result = $member->delete();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when trying to leave a group fails.
            throw new Exception(_("Group leave failed."));
        }

        return true;
    }

    function getMember()
    {
        $member = Profile::staticGet('id', $this->profile_id);

        if (empty($member)) {
            // TRANS: Exception thrown providing an invalid profile ID.
            // TRANS: %s is the invalid profile ID.
            throw new Exception(sprintf(_("Profile ID %s is invalid."),$this->profile_id));
        }

        return $member;
    }

    function getGroup()
    {
        $group  = User_group::staticGet('id', $this->group_id);

        if (empty($group)) {
            // TRANS: Exception thrown providing an invalid group ID.
            // TRANS: %s is the invalid group ID.
            throw new Exception(sprintf(_("Group ID %s is invalid."),$this->group_id));
        }

        return $group;
    }

    /**
     * Get stream of memberships by member
     *
     * @param integer $memberId profile ID of the member to fetch for
     * @param integer $offset   offset from start of stream to get
     * @param integer $limit    number of memberships to get
     *
     * @return Group_member stream of memberships, use fetch() to iterate
     */

    static function byMember($memberId, $offset=0, $limit=GROUPS_PER_PAGE)
    {
        $membership = new Group_member();

        $membership->profile_id = $memberId;

        $membership->orderBy('created DESC');

        $membership->limit($offset, $limit);

        $membership->find();

        return $membership;
    }

    function asActivity()
    {
        $member = $this->getMember();
        $group  = $this->getGroup();

        $act = new Activity();

        $act->id = TagURI::mint('join:%d:%d:%s',
                                $member->id,
                                $group->id,
                                common_date_iso8601($this->created));

        $act->actor     = ActivityObject::fromProfile($member);
        $act->verb      = ActivityVerb::JOIN;
        $act->objects[] = ActivityObject::fromGroup($group);

        $act->time  = strtotime($this->created);
        // TRANS: Activity title.
        $act->title = _("Join");

        // TRANS: Success message for subscribe to group attempt through OStatus.
        // TRANS: %1$s is the member name, %2$s is the subscribed group's name.
        $act->content = sprintf(_('%1$s has joined group %2$s.'),
                                $member->getBestName(),
                                $group->getBestName());

        $url = common_local_url('AtomPubShowMembership',
                                array('profile' => $member->id,
                                      'group' => $group->id));

        $act->selfLink = $url;
        $act->editLink = $url;

        return $act;
    }
}

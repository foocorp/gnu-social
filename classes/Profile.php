<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for profile
 */
class Profile extends Managed_DataObject
{
    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  multiple_key not_null
    public $fullname;                        // text()
    public $profileurl;                      // text()
    public $homepage;                        // text()
    public $bio;                             // text()  multiple_key
    public $location;                        // text()
    public $lat;                             // decimal(10,7)
    public $lon;                             // decimal(10,7)
    public $location_id;                     // int(4)
    public $location_ns;                     // int(4)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        $def = array(
            'description' => 'local and remote users have profiles',
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'nickname' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username', 'collate' => 'utf8mb4_general_ci'),
                'fullname' => array('type' => 'text', 'description' => 'display name', 'collate' => 'utf8mb4_general_ci'),
                'profileurl' => array('type' => 'text', 'description' => 'URL, cached so we dont regenerate'),
                'homepage' => array('type' => 'text', 'description' => 'identifying URL', 'collate' => 'utf8mb4_general_ci'),
                'bio' => array('type' => 'text', 'description' => 'descriptive biography', 'collate' => 'utf8mb4_general_ci'),
                'location' => array('type' => 'text', 'description' => 'physical location', 'collate' => 'utf8mb4_general_ci'),
                'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
                'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
                'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
                'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'profile_nickname_idx' => array('nickname'),
            )
        );

        // Add a fulltext index

        if (common_config('search', 'type') == 'fulltext') {
            $def['fulltext indexes'] = array('nickname' => array('nickname', 'fullname', 'location', 'bio', 'homepage'));
        }

        return $def;
    }

    public static function getByEmail($email)
    {
        // in the future, profiles should have emails stored...
        $user = User::getKV('email', $email);
        if (!($user instanceof User)) {
            throw new NoSuchUserException(array('email'=>$email));
        }
        return $user->getProfile();
    } 

    protected $_user = array();

    public function getUser()
    {
        if (!isset($this->_user[$this->id])) {
            $user = User::getKV('id', $this->id);
            if (!$user instanceof User) {
                throw new NoSuchUserException(array('id'=>$this->id));
            }
            $this->_user[$this->id] = $user;
        }
        return $this->_user[$this->id];
    }

    protected $_group = array();

    public function getGroup()
    {
        if (!isset($this->_group[$this->id])) {
            $group = User_group::getKV('profile_id', $this->id);
            if (!$group instanceof User_group) {
                throw new NoSuchGroupException(array('profile_id'=>$this->id));
            }
            $this->_group[$this->id] = $group;
        }
        return $this->_group[$this->id];
    }

    public function isGroup()
    {
        try {
            $this->getGroup();
            return true;
        } catch (NoSuchGroupException $e) {
            return false;
        }
    }

    public function isPerson()
    {
        // Maybe other things than PERSON and GROUP can have Profiles in the future?
        return !$this->isGroup();
    }

    public function isLocal()
    {
        try {
            $this->getUser();
        } catch (NoSuchUserException $e) {
            return false;
        }
        return true;
    }

    // Returns false if the user has no password (which will always
    // be the case for remote users). This can be the case for OpenID
    // logins or other mechanisms which don't store a password hash.
    public function hasPassword()
    {
        try {
            return $this->getUser()->hasPassword();
        } catch (NoSuchUserException $e) {
            return false;
        }
    }

    public function getObjectType()
    {
        // FIXME: More types... like peopletags and whatever
        if ($this->isGroup()) {
            return ActivityObject::GROUP;
        } else {
            return ActivityObject::PERSON;
        }
    }

    public function getAvatar($width, $height=null)
    {
        return Avatar::byProfile($this, $width, $height);
    }

    public function setOriginal($filename)
    {
        if ($this->isGroup()) {
            // Until Group avatars are handled just like profile avatars.
            return $this->getGroup()->setOriginal($filename);
        }

        $imagefile = new ImageFile(null, Avatar::path($filename));

        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->width = $imagefile->width;
        $avatar->height = $imagefile->height;
        $avatar->mediatype = image_type_to_mime_type($imagefile->type);
        $avatar->filename = $filename;
        $avatar->original = true;
        $avatar->created = common_sql_now();

        // XXX: start a transaction here
        if (!Avatar::deleteFromProfile($this, true) || !$avatar->insert()) {
            // If we can't delete the old avatars, let's abort right here.
            @unlink(Avatar::path($filename));
            return null;
        }

        return $avatar;
    }

    /**
     * Gets either the full name (if filled) or the nickname.
     *
     * @return string
     */
    function getBestName()
    {
        return ($this->fullname) ? $this->fullname : $this->nickname;
    }

    /**
     * Takes the currently scoped profile into account to give a name 
     * to list in notice streams. Preferences may differ between profiles.
     */
    function getStreamName()
    {
        $user = common_current_user();
        if ($user instanceof User && $user->streamNicknames()) {
            return $this->nickname;
        }

        return $this->getBestName();
    }

    /**
     * Gets the full name (if filled) with nickname as a parenthetical, or the nickname alone
     * if no fullname is provided.
     *
     * @return string
     */
    function getFancyName()
    {
        if ($this->fullname) {
            // TRANS: Full name of a profile or group (%1$s) followed by nickname (%2$s) in parentheses.
            return sprintf(_m('FANCYNAME','%1$s (%2$s)'), $this->fullname, $this->nickname);
        } else {
            return $this->nickname;
        }
    }

    /**
     * Get the most recent notice posted by this user, if any.
     *
     * @return mixed Notice or null
     */
    function getCurrentNotice(Profile $scoped=null)
    {
        try {
            $notice = $this->getNotices(0, 1, 0, 0, $scoped);

            if ($notice->fetch()) {
                if ($notice instanceof ArrayWrapper) {
                    // hack for things trying to work with single notices
                    // ...but this shouldn't happen anymore I think. Keeping it for safety...
                    return $notice->_items[0];
                }
                return $notice;
            }
        } catch (PrivateStreamException $e) {
            // Maybe we should let this through if it's handled well upstream
            return null;
        }
        
        return null;
    }

    function getReplies($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0)
    {
        return Reply::stream($this->getID(), $offset, $limit, $since_id, $before_id);
    }

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        //FIXME: Get Profile::current() some other way to avoid possible
        // confusion between current session profile and background processing.
        $stream = new TaggedProfileNoticeStream($this, $tag, Profile::current());

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0, Profile $scoped=null)
    {
        $stream = new ProfileNoticeStream($this, $scoped);

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    function isMember(User_group $group)
    {
    	$groups = $this->getGroups(0, null);
        while ($groups instanceof User_group && $groups->fetch()) {
    	    if ($groups->id == $group->id) {
    	        return true;
    	    }
    	}
    	return false;
    }

    function isAdmin(User_group $group)
    {
        $gm = Group_member::pkeyGet(array('profile_id' => $this->id,
                                          'group_id' => $group->id));
        return (!empty($gm) && $gm->is_admin);
    }

    function isPendingMember($group)
    {
        $request = Group_join_queue::pkeyGet(array('profile_id' => $this->id,
                                                   'group_id' => $group->id));
        return !empty($request);
    }

    function getGroups($offset=0, $limit=PROFILES_PER_PAGE)
    {
        $ids = array();

        $keypart = sprintf('profile:groups:%d', $this->id);

        $idstring = self::cacheGet($keypart);

        if ($idstring !== false) {
            $ids = explode(',', $idstring);
        } else {
            $gm = new Group_member();

            $gm->profile_id = $this->id;

            if ($gm->find()) {
                while ($gm->fetch()) {
                    $ids[] = $gm->group_id;
                }
            }

            self::cacheSet($keypart, implode(',', $ids));
        }

        if (!is_null($offset) && !is_null($limit)) {
            $ids = array_slice($ids, $offset, $limit);
        }

        try {
            return User_group::multiGet('id', $ids);
        } catch (NoResultException $e) {
            return null;    // throw exception when we handle it everywhere
        }
    }

    function getGroupCount() {
        $groups = $this->getGroups(0, null);
        return $groups instanceof User_group
                ? $groups->N
                : 0;
    }

    function isTagged($peopletag)
    {
        $tag = Profile_tag::pkeyGet(array('tagger' => $peopletag->tagger,
                                          'tagged' => $this->id,
                                          'tag'    => $peopletag->tag));
        return !empty($tag);
    }

    function canTag($tagged)
    {
        if (empty($tagged)) {
            return false;
        }

        if ($tagged->id == $this->id) {
            return true;
        }

        $all = common_config('peopletag', 'allow_tagging', 'all');
        $local = common_config('peopletag', 'allow_tagging', 'local');
        $remote = common_config('peopletag', 'allow_tagging', 'remote');
        $subs = common_config('peopletag', 'allow_tagging', 'subs');

        if ($all) {
            return true;
        }

        $tagged_user = $tagged->getUser();
        if (!empty($tagged_user)) {
            if ($local) {
                return true;
            }
        } else if ($subs) {
            return (Subscription::exists($this, $tagged) ||
                    Subscription::exists($tagged, $this));
        } else if ($remote) {
            return true;
        }
        return false;
    }

    function getLists(Profile $scoped=null, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $ids = array();

        $keypart = sprintf('profile:lists:%d', $this->id);

        $idstr = self::cacheGet($keypart);

        if ($idstr !== false) {
            $ids = explode(',', $idstr);
        } else {
            $list = new Profile_list();
            $list->selectAdd();
            $list->selectAdd('id');
            $list->tagger = $this->id;
            $list->selectAdd('id as "cursor"');

            if ($since_id>0) {
               $list->whereAdd('id > '.$since_id);
            }

            if ($max_id>0) {
                $list->whereAdd('id <= '.$max_id);
            }

            if($offset>=0 && !is_null($limit)) {
                $list->limit($offset, $limit);
            }

            $list->orderBy('id DESC');

            if ($list->find()) {
                while ($list->fetch()) {
                    $ids[] = $list->id;
                }
            }

            self::cacheSet($keypart, implode(',', $ids));
        }

        $showPrivate = $this->sameAs($scoped);

        $lists = array();

        foreach ($ids as $id) {
            $list = Profile_list::getKV('id', $id);
            if (!empty($list) &&
                ($showPrivate || !$list->private)) {

                if (!isset($list->cursor)) {
                    $list->cursor = $list->id;
                }

                $lists[] = $list;
            }
        }

        return new ArrayWrapper($lists);
    }

    /**
     * Get tags that other people put on this profile, in reverse-chron order
     *
     * @param Profile        $scoped     User we are requesting as
     * @param int            $offset     Offset from latest
     * @param int            $limit      Max number to get
     * @param datetime       $since_id   max date
     * @param datetime       $max_id     min date
     *
     * @return Profile_list resulting lists
     */

    function getOtherTags(Profile $scoped=null, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $list = new Profile_list();

        $qry = sprintf('select profile_list.*, unix_timestamp(profile_tag.modified) as "cursor" ' .
                       'from profile_tag join profile_list '.
                       'on (profile_tag.tagger = profile_list.tagger ' .
                       '    and profile_tag.tag = profile_list.tag) ' .
                       'where profile_tag.tagged = %d ',
                       $this->id);


        if (!is_null($scoped)) {
            $qry .= sprintf('AND ( ( profile_list.private = false ) ' .
                            'OR ( profile_list.tagger = %d AND ' .
                            'profile_list.private = true ) )',
                            $scoped->getID());
        } else {
            $qry .= 'AND profile_list.private = 0 ';
        }

        if ($since_id > 0) {
            $qry .= sprintf('AND (cursor > %d) ', $since_id);
        }

        if ($max_id > 0) {
            $qry .= sprintf('AND (cursor < %d) ', $max_id);
        }

        $qry .= 'ORDER BY profile_tag.modified DESC ';

        if ($offset >= 0 && !is_null($limit)) {
            $qry .= sprintf('LIMIT %d OFFSET %d ', $limit, $offset);
        }

        $list->query($qry);
        return $list;
    }

    function getPrivateTags($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $tags = new Profile_list();
        $tags->private = true;
        $tags->tagger = $this->id;

        if ($since_id>0) {
           $tags->whereAdd('id > '.$since_id);
        }

        if ($max_id>0) {
            $tags->whereAdd('id <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $tags->limit($offset, $limit);
        }

        $tags->orderBy('id DESC');
        $tags->find();

        return $tags;
    }

    function hasLocalTags()
    {
        $tags = new Profile_tag();

        $tags->joinAdd(array('tagger', 'user:id'));
        $tags->whereAdd('tagged  = '.$this->id);
        $tags->whereAdd('tagger != '.$this->id);

        $tags->limit(0, 1);
        $tags->fetch();

        return ($tags->N == 0) ? false : true;
    }

    function getTagSubscriptions($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $lists = new Profile_list();
        $subs = new Profile_tag_subscription();

        $lists->joinAdd(array('id', 'profile_tag_subscription:profile_tag_id'));

        #@fixme: postgres (round(date_part('epoch', my_date)))
        $lists->selectAdd('unix_timestamp(profile_tag_subscription.created) as "cursor"');

        $lists->whereAdd('profile_tag_subscription.profile_id = '.$this->id);

        if ($since_id>0) {
           $lists->whereAdd('cursor > '.$since_id);
        }

        if ($max_id>0) {
            $lists->whereAdd('cursor <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $lists->limit($offset, $limit);
        }

        $lists->orderBy('"cursor" DESC');
        $lists->find();

        return $lists;
    }

    /**
     * Request to join the given group.
     * May throw exceptions on failure.
     *
     * @param User_group $group
     * @return mixed: Group_member on success, Group_join_queue if pending approval, null on some cancels?
     */
    function joinGroup(User_group $group)
    {
        $join = null;
        if ($group->join_policy == User_group::JOIN_POLICY_MODERATE) {
            $join = Group_join_queue::saveNew($this, $group);
        } else {
            if (Event::handle('StartJoinGroup', array($group, $this))) {
                $join = Group_member::join($group->id, $this->id);
                self::blow('profile:groups:%d', $this->id);
                self::blow('group:member_ids:%d', $group->id);
                self::blow('group:member_count:%d', $group->id);
                Event::handle('EndJoinGroup', array($group, $this));
            }
        }
        if ($join) {
            // Send any applicable notifications...
            $join->notify();
        }
        return $join;
    }

    /**
     * Leave a group that this profile is a member of.
     *
     * @param User_group $group
     */
    function leaveGroup(User_group $group)
    {
        if (Event::handle('StartLeaveGroup', array($group, $this))) {
            Group_member::leave($group->id, $this->id);
            self::blow('profile:groups:%d', $this->id);
            self::blow('group:member_ids:%d', $group->id);
            self::blow('group:member_count:%d', $group->id);
            Event::handle('EndLeaveGroup', array($group, $this));
        }
    }

    function avatarUrl($size=AVATAR_PROFILE_SIZE)
    {
        return Avatar::urlByProfile($this, $size);
    }

    function getSubscribed($offset=0, $limit=null)
    {
        $subs = Subscription::getSubscribedIDs($this->id, $offset, $limit);
        try {
            $profiles = Profile::multiGet('id', $subs);
        } catch (NoResultException $e) {
            return $e->obj;
        }
        return $profiles;
    }

    function getSubscribers($offset=0, $limit=null)
    {
        $subs = Subscription::getSubscriberIDs($this->id, $offset, $limit);
        try {
            $profiles = Profile::multiGet('id', $subs);
        } catch (NoResultException $e) {
            return $e->obj;
        }
        return $profiles;
    }

    function getTaggedSubscribers($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscriber ' .
          'JOIN profile_tag ON (profile_tag.tagged = subscription.subscriber ' .
          'AND profile_tag.tagger = subscription.subscribed) ' .
          'WHERE subscription.subscribed = %d ' .
          "AND profile_tag.tag = '%s' " .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        if ($offset) {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }

        $profile = new Profile();

        $cnt = $profile->query(sprintf($qry, $this->id, $profile->escape($tag)));

        return $profile;
    }

    function getTaggedSubscriptions($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscribed ' .
          'JOIN profile_tag on (profile_tag.tagged = subscription.subscribed ' .
          'AND profile_tag.tagger = subscription.subscriber) ' .
          'WHERE subscription.subscriber = %d ' .
          "AND profile_tag.tag = '%s' " .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $profile = new Profile();

        $profile->query(sprintf($qry, $this->id, $profile->escape($tag)));

        return $profile;
    }

    /**
     * Get pending subscribers, who have not yet been approved.
     *
     * @param int $offset
     * @param int $limit
     * @return Profile
     */
    function getRequests($offset=0, $limit=null)
    {
        // FIXME: mysql only
        $subqueue = new Profile();
        $subqueue->joinAdd(array('id', 'subscription_queue:subscriber'));
        $subqueue->whereAdd(sprintf('subscription_queue.subscribed = %d', $this->getID()));
        $subqueue->limit($offset, $limit);
        $subqueue->orderBy('subscription_queue.created', 'DESC');
        if (!$subqueue->find()) {
            throw new NoResultException($subqueue);
        }
        return $subqueue;
    }

    function subscriptionCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscription_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $cnt = (int) $sub->count('distinct subscribed');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscription_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function subscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscriber_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscribed = $this->id;
        $sub->whereAdd('subscriber != subscribed');
        $cnt = (int) $sub->count('distinct subscriber');

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscriber_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    /**
     * Is this profile subscribed to another profile?
     *
     * @param Profile $other
     * @return boolean
     */
    function isSubscribed(Profile $other)
    {
        return Subscription::exists($this, $other);
    }

    function readableBy(Profile $other=null)
    {
        // If it's not a private stream, it's readable by anyone
        if (!$this->isPrivateStream()) {
            return true;
        }

        // If it's a private stream, $other must be a subscriber to $this
        return is_null($other) ? false : $other->isSubscribed($this);
    }

    function requiresSubscriptionApproval(Profile $other=null)
    {
        if (!$this->isLocal()) {
            // We don't know for remote users, and we'll always be able to send
            // the request. Whether it'll work immediately or require moderation
            // can be determined in another function.
            return false;
        }

        // Assume that profiles _we_ subscribe to are permitted. Could be made configurable.
        if (!is_null($other) && $this->isSubscribed($other)) {
            return false;
        }

        // If the local user either has a private stream (implies the following)
        // or  user has a moderation policy for new subscriptions, return true.
        return $this->getUser()->private_stream || $this->getUser()->subscribe_policy === User::SUBSCRIBE_POLICY_MODERATE;
    }

    /**
     * Check if a pending subscription request is outstanding for this...
     *
     * @param Profile $other
     * @return boolean
     */
    function hasPendingSubscription(Profile $other)
    {
        return Subscription_queue::exists($this, $other);
    }

    /**
     * Are these two profiles subscribed to each other?
     *
     * @param Profile $other
     * @return boolean
     */
    function mutuallySubscribed(Profile $other)
    {
        return $this->isSubscribed($other) &&
          $other->isSubscribed($this);
    }

    function noticeCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:notice_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $notices = new Notice();
        $notices->profile_id = $this->id;
        $notices->verb = ActivityVerb::POST;        
        $cnt = (int) $notices->count('distinct id');

        if (!empty($c)) {
            $c->set(Cache::key('profile:notice_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function blowSubscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscriber_count:'.$this->id));
        }
    }

    function blowSubscriptionCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscription_count:'.$this->id));
        }
    }

    function blowNoticeCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:notice_count:'.$this->id));
        }
    }

    static function maxBio()
    {
        $biolimit = common_config('profile', 'biolimit');
        // null => use global limit (distinct from 0!)
        if (is_null($biolimit)) {
            $biolimit = common_config('site', 'textlimit');
        }
        return $biolimit;
    }

    static function bioTooLong($bio)
    {
        $biolimit = self::maxBio();
        return ($biolimit > 0 && !empty($bio) && (mb_strlen($bio) > $biolimit));
    }

    function update($dataObject=false)
    {
        if (is_object($dataObject) && $this->nickname != $dataObject->nickname) {
            try {
                $local = $this->getUser();
                common_debug("Updating User ({$this->id}) nickname from {$dataObject->nickname} to {$this->nickname}");
                $origuser = clone($local);
                $local->nickname = $this->nickname;
                // updateWithKeys throws exception on failure.
                $local->updateWithKeys($origuser);

                // Clear the site owner, in case nickname changed
                if ($local->hasRole(Profile_role::OWNER)) {
                    User::blow('user:site_owner');
                }
            } catch (NoSuchUserException $e) {
                // Nevermind...
            }
        }

        return parent::update($dataObject);
    }

    public function getRelSelf()
    {
        return ['href' => $this->getUrl(),
                'text' => common_config('site', 'name'),
                'image' => Avatar::urlByProfile($this)];
    }

    // All the known rel="me", used for the IndieWeb audience
    public function getRelMes()
    {
        $relMes = array();
        try {
            $relMes[] = $this->getRelSelf();
        } catch (InvalidUrlException $e) {
            // no valid profile URL available
        }
        if (common_valid_http_url($this->getHomepage())) {
            $relMes[] = ['href' => $this->getHomepage(),
                         'text' => _('Homepage'),
                         'image' => null];
        }
        Event::handle('OtherAccountProfiles', array($this, &$relMes));
        return $relMes;
    }

    function delete($useWhere=false)
    {
        // just in case it hadn't been done before... (usually set before adding deluser to queue handling!)
        if (!$this->hasRole(Profile_role::DELETED)) {
            $this->grantRole(Profile_role::DELETED);
        }

        $this->_deleteNotices();
        $this->_deleteSubscriptions();
        $this->_deleteTags();
        $this->_deleteBlocks();
        $this->_deleteAttentions();
        Avatar::deleteFromProfile($this, true);

        // Warning: delete() will run on the batch objects,
        // not on individual objects.
        $related = array('Reply',
                         'Group_member',
                         );
        Event::handle('ProfileDeleteRelated', array($this, &$related));

        foreach ($related as $cls) {
            $inst = new $cls();
            $inst->profile_id = $this->id;
            $inst->delete();
        }

        $localuser = User::getKV('id', $this->id);
        if ($localuser instanceof User) {
            $localuser->delete();
        }

        return parent::delete($useWhere);
    }

    function _deleteNotices()
    {
        $notice = new Notice();
        $notice->profile_id = $this->id;

        if ($notice->find()) {
            while ($notice->fetch()) {
                $other = clone($notice);
                $other->delete();
            }
        }
    }

    function _deleteSubscriptions()
    {
        $sub = new Subscription();
        $sub->subscriber = $this->getID();
        $sub->find();

        while ($sub->fetch()) {
            try {
                $other = $sub->getSubscribed();
                if (!$other->sameAs($this)) {
                    Subscription::cancel($this, $other);
                }
            } catch (NoResultException $e) {
                // Profile not found
                common_log(LOG_INFO, 'Subscribed profile id=='.$sub->subscribed.' not found when deleting profile id=='.$this->getID().', ignoring...');
            } catch (ServerException $e) {
                // Subscription cancel failed
                common_log(LOG_INFO, 'Subscribed profile id=='.$other->getID().' could not be reached for unsubscription notice when deleting profile id=='.$this->getID().', ignoring...');
            }
        }

        $sub = new Subscription();
        $sub->subscribed = $this->getID();
        $sub->find();

        while ($sub->fetch()) {
            try {
                $other = $sub->getSubscriber();
                common_log(LOG_INFO, 'Subscriber profile id=='.$sub->subscribed.' not found when deleting profile id=='.$this->getID().', ignoring...');
                if (!$other->sameAs($this)) {
                    Subscription::cancel($other, $this);
                }
            } catch (NoResultException $e) {
                // Profile not found
                common_log(LOG_INFO, 'Subscribed profile id=='.$sub->subscribed.' not found when deleting profile id=='.$this->getID().', ignoring...');
            } catch (ServerException $e) {
                // Subscription cancel failed
                common_log(LOG_INFO, 'Subscriber profile id=='.$other->getID().' could not be reached for unsubscription notice when deleting profile id=='.$this->getID().', ignoring...');
            }
        }

        // Finally delete self-subscription
        $self = new Subscription();
        $self->subscriber = $this->getID();
        $self->subscribed = $this->getID();
        $self->delete();
    }

    function _deleteTags()
    {
        $tag = new Profile_tag();
        $tag->tagged = $this->id;
        $tag->delete();
    }

    function _deleteBlocks()
    {
        $block = new Profile_block();
        $block->blocked = $this->id;
        $block->delete();

        $block = new Group_block();
        $block->blocked = $this->id;
        $block->delete();
    }

    function _deleteAttentions()
    {
        $att = new Attention();
        $att->profile_id = $this->getID();

        if ($att->find()) {
            while ($att->fetch()) {
                // Can't do delete() on the object directly since it won't remove all of it
                $other = clone($att);
                $other->delete();
            }
        }
    }

    // XXX: identical to Notice::getLocation.

    public function getLocation()
    {
        $location = null;

        if (!empty($this->location_id) && !empty($this->location_ns)) {
            $location = Location::fromId($this->location_id, $this->location_ns);
        }

        if (is_null($location)) { // no ID, or Location::fromId() failed
            if (!empty($this->lat) && !empty($this->lon)) {
                $location = Location::fromLatLon($this->lat, $this->lon);
            }
        }

        if (is_null($location)) { // still haven't found it!
            if (!empty($this->location)) {
                $location = Location::fromName($this->location);
            }
        }

        return $location;
    }

    public function shareLocation()
    {
        $cfg = common_config('location', 'share');

        if ($cfg == 'always') {
            return true;
        } else if ($cfg == 'never') {
            return false;
        } else { // user
            $share = common_config('location', 'sharedefault');

            // Check if user has a personal setting for this
            $prefs = User_location_prefs::getKV('user_id', $this->id);

            if (!empty($prefs)) {
                $share = $prefs->share_location;
                $prefs->free();
            }

            return $share;
        }
    }

    function hasRole($name)
    {
        $has_role = false;
        if (Event::handle('StartHasRole', array($this, $name, &$has_role))) {
            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));
            $has_role = !empty($role);
            Event::handle('EndHasRole', array($this, $name, $has_role));
        }
        return $has_role;
    }

    function grantRole($name)
    {
        if (Event::handle('StartGrantRole', array($this, $name))) {

            $role = new Profile_role();

            $role->profile_id = $this->id;
            $role->role       = $name;
            $role->created    = common_sql_now();

            $result = $role->insert();

            if (!$result) {
                throw new Exception("Can't save role '$name' for profile '{$this->id}'");
            }

            if ($name == 'owner') {
                User::blow('user:site_owner');
            }

            Event::handle('EndGrantRole', array($this, $name));
        }

        return $result;
    }

    function revokeRole($name)
    {
        if (Event::handle('StartRevokeRole', array($this, $name))) {

            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));

            if (empty($role)) {
                // TRANS: Exception thrown when trying to revoke an existing role for a user that does not exist.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; does not exist.'),$name, $this->id));
            }

            $result = $role->delete();

            if (!$result) {
                common_log_db_error($role, 'DELETE', __FILE__);
                // TRANS: Exception thrown when trying to revoke a role for a user with a failing database query.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; database error.'),$name, $this->id));
            }

            if ($name == 'owner') {
                User::blow('user:site_owner');
            }

            Event::handle('EndRevokeRole', array($this, $name));

            return true;
        }
    }

    function isSandboxed()
    {
        return $this->hasRole(Profile_role::SANDBOXED);
    }

    function isSilenced()
    {
        return $this->hasRole(Profile_role::SILENCED);
    }

    function sandbox()
    {
        $this->grantRole(Profile_role::SANDBOXED);
    }

    function unsandbox()
    {
        $this->revokeRole(Profile_role::SANDBOXED);
    }

    function silence()
    {
        $this->grantRole(Profile_role::SILENCED);
        if (common_config('notice', 'hidespam')) {
            $this->flushVisibility();
        }
    }

    function silenceAs(Profile $actor)
    {
        if (!$actor->hasRight(Right::SILENCEUSER)) {
            throw new AuthorizationException(_('You cannot silence users on this site.'));
        }
        // Only administrators can silence other privileged users (such as others who have the right to silence).
        if ($this->isPrivileged() && !$actor->hasRole(Profile_role::ADMINISTRATOR)) {
            throw new AuthorizationException(_('You cannot silence other privileged users.'));
        }
        if ($this->isSilenced()) {
            // TRANS: Client error displayed trying to silence an already silenced user.
            throw new AlreadyFulfilledException(_('User is already silenced.'));
        }
        return $this->silence();
    }

    function unsilence()
    {
        $this->revokeRole(Profile_role::SILENCED);
        if (common_config('notice', 'hidespam')) {
            $this->flushVisibility();
        }
    }

    function unsilenceAs(Profile $actor)
    {
        if (!$actor->hasRight(Right::SILENCEUSER)) {
            // TRANS: Client error displayed trying to unsilence a user when the user does not have the right.
            throw new AuthorizationException(_('You cannot unsilence users on this site.'));
        }
        if (!$this->isSilenced()) {
            // TRANS: Client error displayed trying to unsilence a user when the target user has not been silenced.
            throw new AlreadyFulfilledException(_('User is not silenced.'));
        }
        return $this->unsilence();
    }

    function flushVisibility()
    {
        // Get all notices
        $stream = new ProfileNoticeStream($this, $this);
        $ids = $stream->getNoticeIds(0, CachingNoticeStream::CACHE_WINDOW);
        foreach ($ids as $id) {
            self::blow('notice:in-scope-for:%d:null', $id);
        }
    }

    public function isPrivileged()
    {
        // TODO: An Event::handle so plugins can report if users are privileged.
        // The ModHelper is the only one I care about when coding this, and that
        // can be tested with Right::SILENCEUSER which I do below:
        switch (true) {
        case $this->hasRight(Right::SILENCEUSER):
        case $this->hasRole(Profile_role::MODERATOR):
        case $this->hasRole(Profile_role::ADMINISTRATOR):
        case $this->hasRole(Profile_role::OWNER):
            return true;
        }

        return false;
    }

    /**
     * Does this user have the right to do X?
     *
     * With our role-based authorization, this is merely a lookup for whether the user
     * has a particular role. The implementation currently uses a switch statement
     * to determine if the user has the pre-defined role to exercise the right. Future
     * implementations may allow per-site roles, and different mappings of roles to rights.
     *
     * @param $right string Name of the right, usually a constant in class Right
     * @return boolean whether the user has the right in question
     */
    public function hasRight($right)
    {
        $result = false;

        if ($this->hasRole(Profile_role::DELETED)) {
            return false;
        }

        if (Event::handle('UserRightsCheck', array($this, $right, &$result))) {
            switch ($right)
            {
            case Right::DELETEOTHERSNOTICE:
            case Right::MAKEGROUPADMIN:
            case Right::SANDBOXUSER:
            case Right::SILENCEUSER:
            case Right::DELETEUSER:
            case Right::DELETEGROUP:
            case Right::TRAINSPAM:
            case Right::REVIEWSPAM:
                $result = $this->hasRole(Profile_role::MODERATOR);
                break;
            case Right::CONFIGURESITE:
                $result = $this->hasRole(Profile_role::ADMINISTRATOR);
                break;
            case Right::GRANTROLE:
            case Right::REVOKEROLE:
                $result = $this->hasRole(Profile_role::OWNER);
                break;
            case Right::NEWNOTICE:
            case Right::NEWMESSAGE:
            case Right::SUBSCRIBE:
            case Right::CREATEGROUP:
                $result = !$this->isSilenced();
                break;
            case Right::PUBLICNOTICE:
            case Right::EMAILONREPLY:
            case Right::EMAILONSUBSCRIBE:
            case Right::EMAILONFAVE:
                $result = !$this->isSandboxed();
                break;
            case Right::WEBLOGIN:
                $result = !$this->isSilenced();
                break;
            case Right::API:
                $result = !$this->isSilenced();
                break;
            case Right::BACKUPACCOUNT:
                $result = common_config('profile', 'backup');
                break;
            case Right::RESTOREACCOUNT:
                $result = common_config('profile', 'restore');
                break;
            case Right::DELETEACCOUNT:
                $result = common_config('profile', 'delete');
                break;
            case Right::MOVEACCOUNT:
                $result = common_config('profile', 'move');
                break;
            default:
                $result = false;
                break;
            }
        }
        return $result;
    }

    // FIXME: Can't put Notice typing here due to ArrayWrapper
    public function hasRepeated($notice)
    {
        // XXX: not really a pkey, but should work

        $notice = Notice::pkeyGet(array('profile_id' => $this->getID(),
                                        'repeat_of' => $notice->getID(),
                                        'verb' => ActivityVerb::SHARE));

        return !empty($notice);
    }

    /**
     * Returns an XML string fragment with limited profile information
     * as an Atom <author> element.
     *
     * Assumes that Atom has been previously set up as the base namespace.
     *
     * @param Profile $cur the current authenticated user
     *
     * @return string
     */
    function asAtomAuthor($cur = null)
    {
        $xs = new XMLStringer(true);

        $xs->elementStart('author');
        $xs->element('name', null, $this->nickname);
        $xs->element('uri', null, $this->getUri());
        if ($cur != null) {
            $attrs = Array();
            $attrs['following'] = $cur->isSubscribed($this) ? 'true' : 'false';
            $attrs['blocking']  = $cur->hasBlocked($this) ? 'true' : 'false';
            $xs->element('statusnet:profile_info', $attrs, null);
        }
        $xs->elementEnd('author');

        return $xs->getString();
    }

    /**
     * Extra profile info for atom entries
     *
     * Clients use some extra profile info in the atom stream.
     * This gives it to them.
     *
     * @param Profile $scoped The currently logged in/scoped profile
     *
     * @return array representation of <statusnet:profile_info> element or null
     */

    function profileInfo(Profile $scoped=null)
    {
        $profileInfoAttr = array('local_id' => $this->id);

        if ($scoped instanceof Profile) {
            // Whether the current user is a subscribed to this profile
            $profileInfoAttr['following'] = $scoped->isSubscribed($this) ? 'true' : 'false';
            // Whether the current user is has blocked this profile
            $profileInfoAttr['blocking']  = $scoped->hasBlocked($this) ? 'true' : 'false';
        }

        return array('statusnet:profile_info', $profileInfoAttr, null);
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams <activity:actor> element.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @return string
     */
    function asActivityActor()
    {
        return $this->asActivityNoun('actor');
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity', 'georss', and 'poco' namespace has been
     * previously defined.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     *
     * @return string
     */
    function asActivityNoun($element)
    {
        $noun = $this->asActivityObject();
        return $noun->asString('activity:' . $element);
    }

    public function asActivityObject()
    {
        $object = new ActivityObject();

        if (Event::handle('StartActivityObjectFromProfile', array($this, &$object))) {
            $object->type   = $this->getObjectType();
            $object->id     = $this->getUri();
            $object->title  = $this->getBestName();
            $object->link   = $this->getUrl();
            $object->summary = $this->getDescription();

            try {
                $avatar = Avatar::getUploaded($this);
                $object->avatarLinks[] = AvatarLink::fromAvatar($avatar);
            } catch (NoAvatarException $e) {
                // Could not find an original avatar to link
            }

            $sizes = array(
                AVATAR_PROFILE_SIZE,
                AVATAR_STREAM_SIZE,
                AVATAR_MINI_SIZE
            );

            foreach ($sizes as $size) {
                $alink  = null;
                try {
                    $avatar = Avatar::byProfile($this, $size);
                    $alink = AvatarLink::fromAvatar($avatar);
                } catch (NoAvatarException $e) {
                    $alink = new AvatarLink();
                    $alink->type   = 'image/png';
                    $alink->height = $size;
                    $alink->width  = $size;
                    $alink->url    = Avatar::defaultImage($size);
                }

                $object->avatarLinks[] = $alink;
            }

            if (isset($this->lat) && isset($this->lon)) {
                $object->geopoint = (float)$this->lat
                    . ' ' . (float)$this->lon;
            }

            $object->poco = PoCo::fromProfile($this);

            if ($this->isLocal()) {
                $object->extra[] = array('followers', array('url' => common_local_url('subscribers', array('nickname' => $this->getNickname()))));
            }

            Event::handle('EndActivityObjectFromProfile', array($this, &$object));
        }

        return $object;
    }

    /**
     * Returns the profile's canonical url, not necessarily a uri/unique id
     *
     * @return string $profileurl
     */
    public function getUrl()
    {
        $url = null;
        if ($this->isGroup()) {
            // FIXME: Get rid of this event, it fills no real purpose, data should be in Profile->profileurl (replaces User_group->mainpage)
            if (Event::handle('StartUserGroupHomeUrl', array($this->getGroup(), &$url))) {
                $url = $this->getGroup()->isLocal()
                        ? common_local_url('showgroup', array('nickname' => $this->getNickname()))
                        : $this->profileurl;
            }
            Event::handle('EndUserGroupHomeUrl', array($this->getGroup(), $url));
        } elseif ($this->isLocal()) {
            $url = common_local_url('showstream', array('nickname' => $this->getNickname()));
        } else {
            $url = $this->profileurl;
        }
        if (empty($url) ||
                !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($url);
        }
        return $url;
    }

    public function getNickname()
    {
        return $this->nickname;
    }

    public function getFullname()
    {
        return $this->fullname;
    }

    public function getHomepage()
    {
        return $this->homepage;
    }

    public function getDescription()
    {
        return $this->bio;
    }

    /**
     * Returns the best URI for a profile. Plugins may override.
     *
     * @return string $uri
     */
    public function getUri()
    {
        $uri = null;

        // give plugins a chance to set the URI
        if (Event::handle('StartGetProfileUri', array($this, &$uri))) {

            // check for a local user first
            $user = User::getKV('id', $this->id);
            if ($user instanceof User) {
                $uri = $user->getUri();
            } else {
                $group = User_group::getKV('profile_id', $this->id);
                if ($group instanceof User_group) {
                    $uri = $group->getUri();
                }
            }

            Event::handle('EndGetProfileUri', array($this, &$uri));
        }

        return $uri;
    }

    /**
     * Returns an assumed acct: URI for a profile. Plugins are required.
     *
     * @return string $uri
     */
    public function getAcctUri()
    {
        $acct = null;

        if (Event::handle('StartGetProfileAcctUri', array($this, &$acct))) {
            Event::handle('EndGetProfileAcctUri', array($this, &$acct));
        }

        if ($acct === null) {
            throw new ProfileNoAcctUriException($this);
        }

        return $acct;
    }

    function hasBlocked($other)
    {
        $block = Profile_block::exists($this, $other);
        return !empty($block);
    }

    function getAtomFeed()
    {
        $feed = null;

        if (Event::handle('StartProfileGetAtomFeed', array($this, &$feed))) {
            $user = User::getKV('id', $this->id);
            if (!empty($user)) {
                $feed = common_local_url('ApiTimelineUser', array('id' => $user->id,
                                                                  'format' => 'atom'));
            }
            Event::handle('EndProfileGetAtomFeed', array($this, $feed));
        }

        return $feed;
    }

    public function repeatedToMe($offset=0, $limit=20, $since_id=null, $max_id=null)
    {
        // TRANS: Exception thrown when trying view "repeated to me".
        throw new Exception(_('Not implemented since inbox change.'));
    }

    /*
     * Get a Profile object by URI. Will call external plugins for help
     * using the event StartGetProfileFromURI.
     *
     * @param string $uri A unique identifier for a resource (profile/group/whatever)
     */
    static function fromUri($uri)
    {
        $profile = null;

        if (Event::handle('StartGetProfileFromURI', array($uri, &$profile))) {
            // Get a local user when plugin lookup (like OStatus) fails
            $user = User::getKV('uri', $uri);
            if ($user instanceof User) {
                $profile = $user->getProfile();
            } else {
                $group = User_group::getKV('uri', $uri);
                if ($group instanceof User_group) {
                    $profile = $group->getProfile();
                }
            }
            Event::handle('EndGetProfileFromURI', array($uri, $profile));
        }

        if (!$profile instanceof Profile) {
            throw new UnknownUriException($uri);
        }

        return $profile;
    }

    function canRead(Notice $notice)
    {
        if ($notice->scope & Notice::SITE_SCOPE) {
            $user = $this->getUser();
            if (empty($user)) {
                return false;
            }
        }

        if ($notice->scope & Notice::ADDRESSEE_SCOPE) {
            $replies = $notice->getReplies();

            if (!in_array($this->id, $replies)) {
                $groups = $notice->getGroups();

                $foundOne = false;

                foreach ($groups as $group) {
                    if ($this->isMember($group)) {
                        $foundOne = true;
                        break;
                    }
                }

                if (!$foundOne) {
                    return false;
                }
            }
        }

        if ($notice->scope & Notice::FOLLOWER_SCOPE) {
            $author = $notice->getProfile();
            if (!Subscription::exists($this, $author)) {
                return false;
            }
        }

        return true;
    }

    static function current()
    {
        $user = common_current_user();
        if (empty($user)) {
            $profile = null;
        } else {
            $profile = $user->getProfile();
        }
        return $profile;
    }

    static function ensureCurrent()
    {
        $profile = self::current();
        if (!$profile instanceof Profile) {
            throw new AuthorizationException('A currently scoped profile is required.');
        }
        return $profile;
    }

    /**
     * Magic function called at serialize() time.
     *
     * We use this to drop a couple process-specific references
     * from DB_DataObject which can cause trouble in future
     * processes.
     *
     * @return array of variable names to include in serialization.
     */

    function __sleep()
    {
        $vars = parent::__sleep();
        $skip = array('_user', '_group');
        return array_diff($vars, $skip);
    }

    public function getProfile()
    {
        return $this;
    }

    /**
     * Test whether the given profile is the same as the current class,
     * for testing identities.
     *
     * @param Profile $other    The other profile, usually from Action's $this->scoped
     *
     * @return boolean
     */
    public function sameAs(Profile $other=null)
    {
        if (is_null($other)) {
            // In case $this->scoped is null or something, i.e. not a current/legitimate profile.
            return false;
        }
        return $this->getID() === $other->getID();
    }

    /**
     * This will perform shortenLinks with the connected User object.
     *
     * Won't work on remote profiles or groups, so expect a
     * NoSuchUserException if you don't know it's a local User.
     *
     * @param string $text      String to shorten
     * @param boolean $always   Disrespect minimum length etc.
     *
     * @return string link-shortened $text
     */
    public function shortenLinks($text, $always=false)
    {
        return $this->getUser()->shortenLinks($text, $always);
    }

    public function isPrivateStream()
    {
        // We only know of public remote users as of yet...
        if (!$this->isLocal()) {
            return false;
        }
        return $this->getUser()->private_stream ? true : false;
    }

    public function delPref($namespace, $topic) {
        return Profile_prefs::setData($this, $namespace, $topic, null);
    }

    public function getPref($namespace, $topic, $default=null) {
        // If you want an exception to be thrown, call Profile_prefs::getData directly
        try {
            return Profile_prefs::getData($this, $namespace, $topic, $default);
        } catch (NoResultException $e) {
            return null;
        }
    }

    // The same as getPref but will fall back to common_config value for the same namespace/topic
    public function getConfigPref($namespace, $topic)
    {
        return Profile_prefs::getConfigData($this, $namespace, $topic);
    }

    public function setPref($namespace, $topic, $data) {
        return Profile_prefs::setData($this, $namespace, $topic, $data);
    }

    public function getConnectedApps($offset=0, $limit=null)
    {
        return $this->getUser()->getConnectedApps($offset, $limit);
    }
}

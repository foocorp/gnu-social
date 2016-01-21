<?php
/**
 * Table Definition for fave
 */

class Fave extends Managed_DataObject
{
    public $__table = 'fave';                            // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $uri;                             // varchar(191)   not 255 because utf8mb4 takes more space   not 255 because utf8mb4 takes more space
    public $created;                         // datetime  multiple_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the favorite'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who likes this notice'),
                'uri' => array('type' => 'varchar', 'length' => 191, 'description' => 'universally unique identifier, usually a tag URI'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id', 'user_id'),
            'unique keys' => array(
                'fave_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'fave_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
                'fave_user_id_fkey' => array('profile', array('user_id' => 'id')), // note: formerly referenced notice.id, but we can now record remote users' favorites
            ),
            'indexes' => array(
                'fave_notice_id_idx' => array('notice_id'),
                'fave_user_id_idx' => array('user_id', 'modified'),
                'fave_modified_idx' => array('modified'),
            ),
        );
    }

    /**
     * Save a favorite record.
     * @fixme post-author notification should be moved here
     *
     * @param Profile $actor  the local or remote Profile who favorites
     * @param Notice  $target the notice that is favorited
     * @return Fave record on success
     * @throws Exception on failure
     */
    static function addNew(Profile $actor, Notice $target) {
        if (self::existsForProfile($target, $actor)) {
            // TRANS: Client error displayed when trying to mark a notice as favorite that already is a favorite.
            throw new AlreadyFulfilledException(_('You have already favorited this!'));
        }

        $act = new Activity();
        $act->type    = ActivityObject::ACTIVITY;
        $act->verb    = ActivityVerb::FAVORITE;
        $act->time    = time();
        $act->id      = self::newUri($actor, $target, common_sql_date($act->time));
        $act->title   = _("Favor");
        // TRANS: Message that is the "content" of a favorite (%1$s is the actor's nickname, %2$ is the favorited
        //        notice's nickname and %3$s is the content of the favorited notice.)
        $act->content = sprintf(_('%1$s favorited something by %2$s: %3$s'),
                                $actor->getNickname(), $target->getProfile()->getNickname(),
                                $target->getRendered());
        $act->actor   = $actor->asActivityObject();
        $act->target  = $target->asActivityObject();
        $act->objects = array(clone($act->target));

        $url = common_local_url('AtomPubShowFavorite', array('profile'=>$actor->id, 'notice'=>$target->id));
        $act->selfLink = $url;
        $act->editLink = $url;

        // saveActivity will in turn also call Fave::saveActivityObject which does
        // what this function used to do before this commit.
        $stored = Notice::saveActivity($act, $actor);

        return $stored;
    }

    public function removeEntry(Profile $actor, Notice $target)
    {
        $fave            = new Fave();
        $fave->user_id   = $actor->getID();
        $fave->notice_id = $target->getID();
        if (!$fave->find(true)) {
            // TRANS: Client error displayed when trying to remove a 'favor' when there is none in the first place.
            throw new AlreadyFulfilledException(_('This is already not favorited.'));
        }

        $result = $fave->delete();
        if ($result === false) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            // TRANS: Server error displayed when removing a favorite from the database fails.
            throw new ServerException(_('Could not delete favorite.'));
        }

        Fave::blowCacheForProfileId($actor->getID());
        Fave::blowCacheForNoticeId($target->getID());
    }

    // exception throwing takeover!
    public function insert()
    {
        if (parent::insert()===false) {
            common_log_db_error($this, 'INSERT', __FILE__);
            throw new ServerException(sprintf(_m('Could not store new object of type %s'), get_called_class()));
        }
        self::blowCacheForProfileId($this->user_id);
        self::blowCacheForNoticeId($this->notice_id);
        return $this;
    }

    public function delete($useWhere=false)
    {
        $result = null;

        try {
            $profile = $this->getActor();
            $notice  = $this->getTarget();

            if (Event::handle('StartDisfavorNotice', array($profile, $notice, &$result))) {

                $result = parent::delete($useWhere);

                if ($result !== false) {
                    Event::handle('EndDisfavorNotice', array($profile, $notice));
                }
            }

        } catch (NoResultException $e) {
            // In case there's some inconsistency where the profile or notice was deleted without losing the fave db entry
            common_log(LOG_INFO, '"'.get_class($e->obj).'" with id=='.var_export($e->obj->id, true).' object not found when deleting favorite, ignoring...');
        } catch (EmptyIdException $e) {
            // Some buggy instances of GNU social have had favorites with notice id==0 stored in the database
            common_log(LOG_INFO, _ve($e->getMessage()));
        }

        // If we catch an exception above, then $result===null because parent::delete only returns an int>=0 or boolean false
        if (is_null($result)) {
            // Delete it without the event, as something is wrong and we don't want it anyway.
            $result = parent::delete($useWhere);
        }

        // Err, apparently we can reference $this->user_id after parent::delete,
        // I guess it's safe because this is the order it was before!
        self::blowCacheForProfileId($this->user_id);
        self::blowCacheForNoticeId($this->notice_id);
        self::blow('popular');

        return $result;
    }

    static function stream($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $own=false, $since_id=0, $max_id=0)
    {
        $stream = new FaveNoticeStream($user_id, $own);

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    function idStream($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $own=false, $since_id=0, $max_id=0)
    {
        $stream = new FaveNoticeStream($user_id, $own);

        return $stream->getNoticeIds($offset, $limit, $since_id, $max_id);
    }

    function asActivity()
    {
        $target = $this->getTarget();
        $actor  = $this->getActor();

        $act = new Activity();

        $act->verb = ActivityVerb::FAVORITE;

        // FIXME: rationalize this with URL below

        $act->id   = $this->getUri();

        $act->time    = strtotime($this->created);
        // TRANS: Activity title when marking a notice as favorite.
        $act->title   = _("Favor");
        // TRANS: Message that is the "content" of a favorite (%1$s is the actor's nickname, %2$ is the favorited
        //        notice's nickname and %3$s is the content of the favorited notice.)
        $act->content = sprintf(_('%1$s favorited something by %2$s: %3$s'),
                                $actor->getNickname(), $target->getProfile()->getNickname(),
                                $target->getRendered());

        $act->actor     = $actor->asActivityObject();
        $act->target    = $target->asActivityObject();
        $act->objects   = array(clone($act->target));

        $url = common_local_url('AtomPubShowFavorite',
                                          array('profile' => $actor->id,
                                                'notice'  => $target->id));

        $act->selfLink = $url;
        $act->editLink = $url;

        return $act;
    }

    static function existsForProfile($notice, Profile $scoped)
    {
        $fave = self::pkeyGet(array('user_id'=>$scoped->id, 'notice_id'=>$notice->id));

        return ($fave instanceof Fave);
    }

    /**
     * Fetch a stream of favorites by profile
     *
     * @param integer $profileId Profile that faved
     * @param integer $offset    Offset from last
     * @param integer $limit     Number to get
     *
     * @return mixed stream of faves, use fetch() to iterate
     *
     * @todo Cache results
     * @todo integrate with Fave::stream()
     */

    static function byProfile($profileId, $offset, $limit)
    {
        $fav = new Fave();

        $fav->user_id = $profileId;

        $fav->orderBy('modified DESC');

        $fav->limit($offset, $limit);

        $fav->find();

        return $fav;
    }

    static function countByProfile(Profile $profile)
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('fave:count_by_profile:'.$profile->id));
            if (is_integer($cnt)) {
                return $cnt;
            }
        }

        $faves = new Fave();
        $faves->user_id = $profile->id;
        $cnt = (int) $faves->count('notice_id');

        if (!empty($c)) {
            $c->set(Cache::key('fave:count_by_profile:'.$profile->id), $cnt);
        }

        return $cnt;
    }

    static protected $_faves = array();

    /**
     * All faves of this notice
     *
     * @param Notice $notice A notice we wish to get faves for (may still be ArrayWrapper)
     *
     * @return array Array of Fave objects
     */
    static public function byNotice($notice)
    {
        if (!isset(self::$_faves[$notice->id])) {
            self::fillFaves(array($notice->id));
        }
        return self::$_faves[$notice->id];
    }

    static public function fillFaves(array $notice_ids)
    {
        $faveMap = Fave::listGet('notice_id', $notice_ids);
        self::$_faves = array_replace(self::$_faves, $faveMap);
    }

    static public function blowCacheForProfileId($profile_id)
    {
        $cache = Cache::instance();
        if ($cache) {
            // Faves don't happen chronologically, so we need to blow
            // ;last cache, too
            $cache->delete(Cache::key('fave:ids_by_user:'.$profile_id));
            $cache->delete(Cache::key('fave:ids_by_user:'.$profile_id.';last'));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$profile_id));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$profile_id.';last'));
            $cache->delete(Cache::key('fave:count_by_profile:'.$profile_id));
        }
    }
    static public function blowCacheForNoticeId($notice_id)
    {
        $cache = Cache::instance();
        if ($cache) {
            $cache->delete(Cache::key('fave:list-ids:notice_id:'.$notice_id));
        }
    }

    // Remember that we want the _activity_ notice here, not faves applied
    // to the supplied Notice (as with byNotice)!
    static public function fromStored(Notice $stored)
    {
        $class = get_called_class();
        $object = new $class;
        $object->uri = $stored->uri;
        if (!$object->find(true)) {
            throw new NoResultException($object);
        }
        return $object;
    }

    /**
     * Retrieves the _targeted_ notice of a verb (such as the notice that was
     * _favorited_, but not the favorite activity itself).
     *
     * @param Notice $stored    The activity notice.
     *
     * @throws NoResultException when it can't find what it's looking for.
     */
    static public function getTargetFromStored(Notice $stored)
    {
        return self::fromStored($stored)->getTarget();
    }

    static public function getObjectType()
    {
        return ActivityObject::ACTIVITY;
    }

    public function asActivityObject(Profile $scoped=null)
    {
        $actobj = new ActivityObject();
        $actobj->id = $this->getUri();
        $actobj->type = ActivityUtils::resolveUri(self::getObjectType());
        $actobj->actor = $this->getActorObject();
        $actobj->target = $this->getTargetObject();
        $actobj->objects = array(clone($actobj->target));
        $actobj->verb = ActivityVerb::FAVORITE;
        $actobj->title = ActivityUtils::verbToTitle($actobj->verb);
        $actobj->content = $this->getTarget()->getRendered();
        return $actobj;
    }

    /**
     * @param ActivityObject $actobj The _favored_ notice (which we're "in-reply-to")
     * @param Notice         $stored The _activity_ notice, i.e. the favor itself.
     */
    static public function parseActivityObject(ActivityObject $actobj, Notice $stored)
    {
        // throws exception if nothing was found, but it could also be a non-Notice...
        // FIXME: This should only test _one_ URI (and not the links etc.) though a function like this could be useful in other cases
        $local = ActivityUtils::findLocalObject($actobj->getIdentifiers());
        if (!$local instanceof Notice) {
            // $local always returns something, but this was not what we expected. Something is wrong.
            throw new Exception('Something other than a Notice was returned from findLocalObject');
        }
 
        $actor = $stored->getProfile();
        $object = new Fave();
        $object->user_id = $stored->getProfile()->id;
        $object->notice_id = $local->id;
        $object->uri = $stored->uri;
        $object->created = $stored->created;
        $object->modified = $stored->modified;
        return $object;
    }

    static public function extendActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        $target = self::getTargetFromStored($stored);

        // The following logic was copied from StatusNet's Activity plugin
        if (ActivityUtils::compareVerbs($target->verb, array(ActivityVerb::POST))) {
            // "I like the thing you posted"
            $act->objects = $target->asActivity()->objects;
        } else {
            // "I like that you did whatever you did"
            $act->target = $target->asActivityObject();
            $act->objects = array(clone($act->target));
        }
        $act->context->replyToID = $target->getUri();
        $act->context->replyToUrl = $target->getUrl();
        $act->title = ActivityUtils::verbToTitle($act->verb);
    }

    static function saveActivityObject(ActivityObject $actobj, Notice $stored)
    {
        $object = self::parseActivityObject($actobj, $stored);
        $object->insert();  // exception throwing in Fave's case!

        self::blowCacheForProfileId($object->user_id);
        self::blowCacheForNoticeId($object->notice_id);
        self::blow('popular');

        Event::handle('EndFavorNotice', array($stored->getProfile(), $object->getTarget()));
        return $object;
    }

    public function getTarget()
    {
        return Notice::getByID($this->notice_id);
    }

    public function getTargetObject()
    {
        return $this->getTarget()->asActivityObject();
    }

    protected $_stored = array();

    public function getStored()
    {
        if (!isset($this->_stored[$this->uri])) {
            $stored = new Notice();
            $stored->uri = $this->uri;
            if (!$stored->find(true)) {
                throw new NoResultException($stored);
            }
            $this->_stored[$this->uri] = $stored;
        }
        return $this->_stored[$this->uri];
    }

    public function getActor()
    {
        return Profile::getByID($this->user_id);
    }

    public function getActorObject()
    {
        return $this->getActor()->asActivityObject();
    }

    public function getUri()
    {
        if (!empty($this->uri)) {
            return $this->uri;
        }

        // We (should've in this case) created it ourselves, so we tag it ourselves
        return self::newUri($this->getActor(), $this->getTarget(), $this->created);
    }
}

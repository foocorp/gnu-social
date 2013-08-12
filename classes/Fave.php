<?php
/**
 * Table Definition for fave
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Fave extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave';                            // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $uri;                             // varchar(255)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the favorite'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who likes this notice'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
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
     * @param Profile $profile the local or remote user who likes
     * @param Notice $notice the notice that is liked
     * @return mixed false on failure, or Fave record on success
     */
    static function addNew(Profile $profile, Notice $notice) {

        $fave = null;

        if (Event::handle('StartFavorNotice', array($profile, $notice, &$fave))) {

            $fave = new Fave();

            $fave->user_id   = $profile->id;
            $fave->notice_id = $notice->id;
            $fave->modified  = common_sql_now();
            $fave->uri       = self::newURI($fave->user_id,
                                            $fave->notice_id,
                                            $fave->modified);
            if (!$fave->insert()) {
                common_log_db_error($fave, 'INSERT', __FILE__);
                return false;
            }
            self::blow('fave:list-ids:notice_id:%d', $fave->notice_id);
            self::blow('popular');

            Event::handle('EndFavorNotice', array($profile, $notice));
        }

        return $fave;
    }

    function delete()
    {
        $profile = Profile::staticGet('id', $this->user_id);
        $notice  = Notice::staticGet('id', $this->notice_id);

        $result = null;

        if (Event::handle('StartDisfavorNotice', array($profile, $notice, &$result))) {

            $result = parent::delete();
            self::blow('fave:list-ids:notice_id:%d', $this->notice_id);
            self::blow('popular');

            if ($result) {
                Event::handle('EndDisfavorNotice', array($profile, $notice));
            }
        }

        return $result;
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Fave', $kv);
    }

    function stream($user_id, $offset=0, $limit=NOTICES_PER_PAGE, $own=false, $since_id=0, $max_id=0)
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
        $notice = Notice::staticGet('id', $this->notice_id);

        if (!$notice) {
            throw new Exception("Fave for non-existent notice: " . $this->notice_id);
        }

        $profile = Profile::staticGet('id', $this->user_id);

        if (!$profile) {
            throw new Exception("Fave by non-existent profile: " . $this->user_id);
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FAVORITE;

        // FIXME: rationalize this with URL below

        $act->id   = $this->getURI();

        $act->time    = strtotime($this->modified);
        // TRANS: Activity title when marking a notice as favorite.
        $act->title   = _("Favor");
        // TRANS: Ntofication given when a user marks a notice as favorite.
        // TRANS: %1$s is a user nickname or full name, %2$s is a notice URI.
        $act->content = sprintf(_('%1$s marked notice %2$s as a favorite.'),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor     = ActivityObject::fromProfile($profile);
        $act->objects[] = ActivityObject::fromNotice($notice);

        $url = common_local_url('AtomPubShowFavorite',
                                          array('profile' => $this->user_id,
                                                'notice'  => $this->notice_id));

        $act->selfLink = $url;
        $act->editLink = $url;

        return $act;
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

    function getURI()
    {
        if (!empty($this->uri)) {
            return $this->uri;
        } else {
            return self::newURI($this->user_id, $this->notice_id, $this->modified);
        }
    }

    static function newURI($profile_id, $notice_id, $modified)
    {
        return TagURI::mint('favor:%d:%d:%s',
                            $profile_id,
                            $notice_id,
                            common_date_iso8601($modified));
    }
}

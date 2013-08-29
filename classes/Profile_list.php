<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 *
 * @category Notices
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Table Definition for profile_list
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_list extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_list';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $tagger;                          // int(4)
    public $tag;                             // varchar(64)
    public $description;                     // text
    public $private;                         // tinyint(1)
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $uri;                             // varchar(255)  unique_key
    public $mainpage;                        // varchar(255)
    public $tagged_count;                    // smallint
    public $subscriber_count;                // smallint

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'tagger' => array('type' => 'int', 'not null' => true, 'description' => 'user making the tag'),
                'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'people tag'),
                'description' => array('type' => 'text', 'description' => 'description of the people tag'),
                'private' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'is this tag private'),

                'created' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date the tag was added'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date the tag was modified'),

                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universal identifier'),
                'mainpage' => array('type' => 'varchar', 'length' => 255, 'description' => 'page to link to'),
                'tagged_count' => array('type' => 'int', 'default' => 0, 'description' => 'number of people tagged with this tag by this user'),
                'subscriber_count' => array('type' => 'int', 'default' => 0, 'description' => 'number of subscribers to this tag'),
            ),
            'primary key' => array('tagger', 'tag'),
            'unique keys' => array(
                'profile_list_id_key' => array('id')
            ),
            'foreign keys' => array(
                'profile_list_tagger_fkey' => array('profile', array('tagger' => 'id')),
            ),
            'indexes' => array(
                'profile_list_modified_idx' => array('modified'),
                'profile_list_tag_idx' => array('tag'),
                'profile_list_tagger_tag_idx' => array('tagger', 'tag'),
                'profile_list_tagged_count_idx' => array('tagged_count'),
                'profile_list_subscriber_count_idx' => array('subscriber_count'),
            ),
        );
    }

    /**
     * get the tagger of this profile_list object
     *
     * @return Profile the tagger
     */

    function getTagger()
    {
        return Profile::getKV('id', $this->tagger);
    }

    /**
     * return a string to identify this
     * profile_list in the user interface etc.
     *
     * @return String
     */

    function getBestName()
    {
        return $this->tag;
    }

    /**
     * return a uri string for this profile_list
     *
     * @return String uri
     */

    function getUri()
    {
        $uri = null;
        if (Event::handle('StartProfiletagGetUri', array($this, &$uri))) {
            if (!empty($this->uri)) {
                $uri = $this->uri;
            } else {
                $uri = common_local_url('profiletagbyid',
                                        array('id' => $this->id, 'tagger_id' => $this->tagger));
            }
        }
        Event::handle('EndProfiletagGetUri', array($this, &$uri));
        return $uri;
    }

    /**
     * return a url to the homepage of this item
     *
     * @return String home url
     */

    function homeUrl()
    {
        $url = null;
        if (Event::handle('StartUserPeopletagHomeUrl', array($this, &$url))) {
            // normally stored in mainpage, but older ones may be null
            if (!empty($this->mainpage)) {
                $url = $this->mainpage;
            } else {
                $url = common_local_url('showprofiletag',
                                        array('tagger' => $this->getTagger()->nickname,
                                              'tag'    => $this->tag));
            }
        }
        Event::handle('EndUserPeopletagHomeUrl', array($this, &$url));
        return $url;
    }

    /**
     * return an immutable url for this object
     *
     * @return String permalink
     */

    function permalink()
    {
        $url = null;
        if (Event::handle('StartProfiletagPermalink', array($this, &$url))) {
            $url = common_local_url('profiletagbyid',
                                    array('id' => $this->id));
        }
        Event::handle('EndProfiletagPermalink', array($this, &$url));
        return $url;
    }

    /**
     * Query notices by users associated with this tag,
     * but first check the cache before hitting the DB.
     *
     * @param integer $offset   offset
     * @param integer $limit    maximum no of results
     * @param integer $since_id=null    since this id
     * @param integer $max_id=null  maximum id in result
     *
     * @return Notice the query
     */

    function getNotices($offset, $limit, $since_id=null, $max_id=null)
    {
        $stream = new PeopletagNoticeStream($this);

        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    /**
     * Get subscribers (local and remote) to this people tag
     * Order by reverse chronology
     *
     * @param integer $offset   offset
     * @param integer $limit    maximum no of results
     * @param integer $since_id=null    since unix timestamp
     * @param integer $upto=null  maximum unix timestamp when subscription was made
     *
     * @return Profile results
     */

    function getSubscribers($offset=0, $limit=null, $since=0, $upto=0)
    {
        $subs = new Profile();

        $subs->joinAdd(
            array('id', 'profile_tag_subscription:profile_id')
        );
        $subs->whereAdd('profile_tag_subscription.profile_tag_id = ' . $this->id);

        $subs->selectAdd('unix_timestamp(profile_tag_subscription.' .
                         'created) as "cursor"');

        if ($since != 0) {
            $subs->whereAdd('cursor > ' . $since);
        }

        if ($upto != 0) {
            $subs->whereAdd('cursor <= ' . $upto);
        }

        if ($limit != null) {
            $subs->limit($offset, $limit);
        }

        $subs->orderBy('profile_tag_subscription.created DESC');
        $subs->find();

        return $subs;
    }

    /**
     * Get all and only local subscribers to this people tag
     * used for distributing notices to user inboxes.
     *
     * @return array ids of users
     */

    function getUserSubscribers()
    {
        // XXX: cache this

        $user = new User();
        if(common_config('db','quote_identifiers'))
            $user_table = '"user"';
        else $user_table = 'user';

        $qry =
          'SELECT id ' .
          'FROM '. $user_table .' JOIN profile_tag_subscription '.
          'ON '. $user_table .'.id = profile_tag_subscription.profile_id ' .
          'WHERE profile_tag_subscription.profile_tag_id = %d ';

        $user->query(sprintf($qry, $this->id));

        $ids = array();

        while ($user->fetch()) {
            $ids[] = $user->id;
        }

        $user->free();

        return $ids;
    }

    /**
     * Check to see if a given profile has
     * subscribed to this people tag's timeline
     *
     * @param mixed $id User or Profile object or integer id
     *
     * @return boolean subscription status
     */

    function hasSubscriber($id)
    {
        if (!is_numeric($id)) {
            $id = $id->id;
        }

        $sub = Profile_tag_subscription::pkeyGet(array('profile_tag_id' => $this->id,
                                                       'profile_id'     => $id));
        return !empty($sub);
    }

    /**
     * Get profiles tagged with this people tag,
     * include modified timestamp as a "cursor" field
     * order by descending order of modified time
     *
     * @param integer $offset   offset
     * @param integer $limit    maximum no of results
     * @param integer $since_id=null    since unix timestamp
     * @param integer $upto=null  maximum unix timestamp when subscription was made
     *
     * @return Profile results
     */

    function getTagged($offset=0, $limit=null, $since=0, $upto=0)
    {
        $tagged = new Profile();
        $tagged->joinAdd(array('id', 'profile_tag:tagged'));

        #@fixme: postgres
        $tagged->selectAdd('unix_timestamp(profile_tag.modified) as "cursor"');
        $tagged->whereAdd('profile_tag.tagger = '.$this->tagger);
        $tagged->whereAdd("profile_tag.tag = '{$this->tag}'");

        if ($since != 0) {
            $tagged->whereAdd('cursor > ' . $since);
        }

        if ($upto != 0) {
            $tagged->whereAdd('cursor <= ' . $upto);
        }

        if ($limit != null) {
            $tagged->limit($offset, $limit);
        }

        $tagged->orderBy('profile_tag.modified DESC');
        $tagged->find();

        return $tagged;
    }

    /**
     * Gracefully delete one or many people tags
     * along with their members and subscriptions data
     *
     * @return boolean success
     */

    function delete()
    {
        // force delete one item at a time.
        if (empty($this->id)) {
            $this->find();
            while ($this->fetch()) {
                $this->delete();
            }
        }

        Profile_tag::cleanup($this);
        Profile_tag_subscription::cleanup($this);

        self::blow('profile:lists:%d', $this->tagger);

        return parent::delete();
    }

    /**
     * Update a people tag gracefully
     * also change "tag" fields in profile_tag table
     *
     * @param Profile_list $orig    Object's original form
     *
     * @return boolean success
     */

    function update($orig=null)
    {
        $result = true;

        if (!is_object($orig) && !$orig instanceof Profile_list) {
            parent::update($orig);
        }

        // if original tag was different
        // check to see if the new tag already exists
        // if not, rename the tag correctly
        if($orig->tag != $this->tag || $orig->tagger != $this->tagger) {
            $existing = Profile_list::getByTaggerAndTag($this->tagger, $this->tag);
            if(!empty($existing)) {
                // TRANS: Server exception.
                throw new ServerException(_('The tag you are trying to rename ' .
                                            'to already exists.'));
            }
            // move the tag
            // XXX: allow OStatus plugin to send out profile tag
            $result = Profile_tag::moveTag($orig, $this);
        }
        parent::update($orig);
        return $result;
    }

    /**
     * return an xml string representing this people tag
     * as the author of an atom feed
     *
     * @return string atom author element
     */

    function asAtomAuthor()
    {
        $xs = new XMLStringer(true);

        $tagger = $this->getTagger();
        $xs->elementStart('author');
        $xs->element('name', null, '@' . $tagger->nickname . '/' . $this->tag);
        $xs->element('uri', null, $this->permalink());
        $xs->elementEnd('author');

        return $xs->getString();
    }

    /**
     * return an xml string to represent this people tag
     * as the subject of an activitystreams feed.
     *
     * @return string activitystreams subject
     */

    function asActivitySubject()
    {
        return $this->asActivityNoun('subject');
    }

    /**
     * return an xml string to represent this people tag
     * as a noun in an activitystreams feed.
     *
     * @param string $element the xml tag
     *
     * @return string activitystreams noun
     */

    function asActivityNoun($element)
    {
        $noun = ActivityObject::fromPeopletag($this);
        return $noun->asString('activity:' . $element);
    }

    /**
     * get the cached number of profiles tagged with this
     * people tag, re-count if the argument is true.
     *
     * @param boolean $recount  whether to ignore cache
     *
     * @return integer count
     */

    function taggedCount($recount=false)
    {
        $keypart = sprintf('profile_list:tagged_count:%d:%s', 
                           $this->tagger,
                           $this->tag);

        $count = self::cacheGet($keypart);

        if ($count === false) {
            $tags = new Profile_tag();

            $tags->tag = $this->tag;
            $tags->tagger = $this->tagger;

            $count = $tags->count('distinct tagged');

            self::cacheSet($keypart, $count);
        }

        return $count;
    }

    /**
     * get the cached number of profiles subscribed to this
     * people tag, re-count if the argument is true.
     *
     * @param boolean $recount  whether to ignore cache
     *
     * @return integer count
     */

    function subscriberCount($recount=false)
    {
        $keypart = sprintf('profile_list:subscriber_count:%d', 
                           $this->id);

        $count = self::cacheGet($keypart);

        if ($count === false) {

            $sub = new Profile_tag_subscription();
            $sub->profile_tag_id = $this->id;
            $count = (int) $sub->count('distinct profile_id');

            self::cacheSet($keypart, $count);
        }

        return $count;
    }

    /**
     * get the cached number of profiles subscribed to this
     * people tag, re-count if the argument is true.
     *
     * @param boolean $recount  whether to ignore cache
     *
     * @return integer count
     */

    function blowNoticeStreamCache($all=false)
    {
        self::blow('profile_list:notice_ids:%d', $this->id);
        if ($all) {
            self::blow('profile_list:notice_ids:%d;last', $this->id);
        }
    }

    /**
     * get the Profile_list object by the
     * given tagger and with given tag
     *
     * @param integer $tagger   the id of the creator profile
     * @param integer $tag      the tag
     *
     * @return integer count
     */

    static function getByTaggerAndTag($tagger, $tag)
    {
        $ptag = Profile_list::pkeyGet(array('tagger' => $tagger, 'tag' => $tag));
        return $ptag;
    }

    /**
     * create a profile_list record for a tag, tagger pair
     * if it doesn't exist, return it.
     *
     * @param integer $tagger   the tagger
     * @param string  $tag      the tag
     * @param string  $description description
     * @param boolean $private  protected or not
     *
     * @return Profile_list the people tag object
     */

    static function ensureTag($tagger, $tag, $description=null, $private=false)
    {
        $ptag = Profile_list::getByTaggerAndTag($tagger, $tag);

        if(empty($ptag->id)) {
            $args = array(
                'tag' => $tag,
                'tagger' => $tagger,
                'description' => $description,
                'private' => $private
            );

            $new_tag = Profile_list::saveNew($args);

            return $new_tag;
        }
        return $ptag;
    }

    /**
     * get the maximum number of characters
     * that can be used in the description of
     * a people tag.
     *
     * determined by $config['peopletag']['desclimit']
     * if not set, falls back to $config['site']['textlimit']
     *
     * @return integer maximum number of characters
     */

    static function maxDescription()
    {
        $desclimit = common_config('peopletag', 'desclimit');
        // null => use global limit (distinct from 0!)
        if (is_null($desclimit)) {
            $desclimit = common_config('site', 'textlimit');
        }
        return $desclimit;
    }

    /**
     * check if the length of given text exceeds
     * character limit.
     *
     * @param string $desc  the description
     *
     * @return boolean is the descripition too long?
     */

    static function descriptionTooLong($desc)
    {
        $desclimit = self::maxDescription();
        return ($desclimit > 0 && !empty($desc) && (mb_strlen($desc) > $desclimit));
    }

    /**
     * save a new people tag, this should be always used
     * since it makes uri, homeurl, created and modified
     * timestamps and performs checks.
     *
     * @param array $fields an array with fields and their values
     *
     * @return mixed Profile_list on success, false on fail
     */
    static function saveNew(array $fields) {
        extract($fields);

        $ptag = new Profile_list();

        $ptag->query('BEGIN');

        if (empty($tagger)) {
            // TRANS: Server exception saving new tag without having a tagger specified.
            throw new Exception(_('No tagger specified.'));
        }

        if (empty($tag)) {
            // TRANS: Server exception saving new tag without having a tag specified.
            throw new Exception(_('No tag specified.'));
        }

        if (empty($mainpage)) {
            $mainpage = null;
        }

        if (empty($uri)) {
            // fill in later...
            $uri = null;
        }

        if (empty($mainpage)) {
            $mainpage = null;
        }

        if (empty($description)) {
            $description = null;
        }

        if (empty($private)) {
            $private = false;
        }

        $ptag->tagger      = $tagger;
        $ptag->tag         = $tag;
        $ptag->description = $description;
        $ptag->private     = $private;
        $ptag->uri         = $uri;
        $ptag->mainpage    = $mainpage;
        $ptag->created     = common_sql_now();
        $ptag->modified    = common_sql_now();

        $result = $ptag->insert();

        if (!$result) {
            common_log_db_error($ptag, 'INSERT', __FILE__);
            // TRANS: Server exception saving new tag.
            throw new ServerException(_('Could not create profile tag.'));
        }

        if (!isset($uri) || empty($uri)) {
            $orig = clone($ptag);
            $ptag->uri = common_local_url('profiletagbyid', array('id' => $ptag->id, 'tagger_id' => $ptag->tagger));
            $result = $ptag->update($orig);
            if (!$result) {
                common_log_db_error($ptag, 'UPDATE', __FILE__);
            // TRANS: Server exception saving new tag.
                throw new ServerException(_('Could not set profile tag URI.'));
            }
        }

        if (!isset($mainpage) || empty($mainpage)) {
            $orig = clone($ptag);
            $user = User::getKV('id', $ptag->tagger);
            if(!empty($user)) {
                $ptag->mainpage = common_local_url('showprofiletag', array('tag' => $ptag->tag, 'tagger' => $user->nickname));
            } else {
                $ptag->mainpage = $uri; // assume this is a remote peopletag and the uri works
            }

            $result = $ptag->update($orig);
            if (!$result) {
                common_log_db_error($ptag, 'UPDATE', __FILE__);
                // TRANS: Server exception saving new tag.
                throw new ServerException(_('Could not set profile tag mainpage.'));
            }
        }
        return $ptag;
    }

    /**
     * get all items at given cursor position for api
     *
     * @param callback $fn  a function that takes the following arguments in order:
     *                      $offset, $limit, $since_id, $max_id
     *                      and returns a Profile_list object after making the DB query
     * @param array $args   arguments required for $fn
     * @param integer $cursor   the cursor
     * @param integer $count    max. number of results
     *
     * Algorithm:
     * - if cursor is 0, return empty list
     * - if cursor is -1, get first 21 items, next_cursor = 20th prev_cursor = 0
     * - if cursor is +ve get 22 consecutive items before starting at cursor
     *   - return items[1..20] if items[0] == cursor else return items[0..21]
     *   - prev_cursor = items[1]
     *   - next_cursor = id of the last item being returned
     *
     * - if cursor is -ve get 22 consecutive items after cursor starting at cursor
     *   - return items[1..20]
     *
     * @returns array (array (mixed items), int next_cursor, int previous_cursor)
     */

     // XXX: This should be in Memcached_DataObject... eventually.

    static function getAtCursor($fn, array $args, $cursor, $count=20)
    {
        $items = array();

        $since_id = 0;
        $max_id = 0;
        $next_cursor = 0;
        $prev_cursor = 0;

        if($cursor > 0) {
            // if cursor is +ve fetch $count+2 items before cursor starting at cursor
            $max_id = $cursor;
            $fn_args = array_merge($args, array(0, $count+2, 0, $max_id));
            $list = call_user_func_array($fn, $fn_args);
            while($list->fetch()) {
                $items[] = clone($list);
            }

            if ((isset($items[0]->cursor) && $items[0]->cursor == $cursor) ||
                $items[0]->id == $cursor) {
                array_shift($items);
                $prev_cursor = isset($items[0]->cursor) ?
                    -$items[0]->cursor : -$items[0]->id;
            } else {
                if (count($items) > $count+1) {
                    array_shift($items);
                }
                // this means the cursor item has been deleted, check to see if there are more
                $fn_args = array_merge($args, array(0, 1, $cursor));
                $more = call_user_func($fn, $fn_args);
                if (!$more->fetch() || empty($more)) {
                    // no more items.
                    $prev_cursor = 0;
                } else {
                    $prev_cursor = isset($items[0]->cursor) ?
                        -$items[0]->cursor : -$items[0]->id;
                }
            }

            if (count($items)==$count+1) {
                // this means there is a next page.
                $next = array_pop($items);
                $next_cursor = isset($next->cursor) ?
                    $items[$count-1]->cursor : $items[$count-1]->id;
            }

        } else if($cursor < -1) {
            // if cursor is -ve fetch $count+2 items created after -$cursor-1
            $cursor = abs($cursor);
            $since_id = $cursor-1;

            $fn_args = array_merge($args, array(0, $count+2, $since_id));
            $list = call_user_func_array($fn, $fn_args);
            while($list->fetch()) {
                $items[] = clone($list);
            }

            $end = count($items)-1;
            if ((isset($items[$end]->cursor) && $items[$end]->cursor == $cursor) ||
                $items[$end]->id == $cursor) {
                array_pop($items);
                $next_cursor = isset($items[$end-1]->cursor) ?
                    $items[$end-1]->cursor : $items[$end-1]->id;
            } else {
                $next_cursor = isset($items[$end]->cursor) ?
                    $items[$end]->cursor : $items[$end]->id;
                if ($end > $count) array_pop($items); // excess item.

                // check if there are more items for next page
                $fn_args = array_merge($args, array(0, 1, 0, $cursor));
                $more = call_user_func_array($fn, $fn_args);
                if (!$more->fetch() || empty($more)) {
                    $next_cursor = 0;
                }
            }

            if (count($items) == $count+1) {
                // this means there is a previous page.
                $prev = array_shift($items);
                $prev_cursor = isset($prev->cursor) ?
                    -$items[0]->cursor : -$items[0]->id;
            }
        } else if($cursor == -1) {
            $fn_args = array_merge($args, array(0, $count+1));
            $list = call_user_func_array($fn, $fn_args);

            while($list->fetch()) {
                $items[] = clone($list);
            }

            if (count($items)==$count+1) {
                $next = array_pop($items);
                if(isset($next->cursor)) {
                    $next_cursor = $items[$count-1]->cursor;
                } else {
                    $next_cursor = $items[$count-1]->id;
                }
            }

        }
        return array($items, $next_cursor, $prev_cursor);
    }

    /**
     * save a collection of people tags into the cache
     *
     * @param string $ckey  cache key
     * @param Profile_list &$tag the results to store
     * @param integer $offset   offset for slicing results
     * @param integer $limit    maximum number of results
     *
     * @return boolean success
     */

    static function setCache($ckey, &$tag, $offset=0, $limit=null) {
        $cache = Cache::instance();
        if (empty($cache)) {
            return false;
        }
        $str = '';
        $tags = array();
        while ($tag->fetch()) {
            $str .= $tag->tagger . ':' . $tag->tag . ';';
            $tags[] = clone($tag);
        }
        $str = substr($str, 0, -1);
        if ($offset>=0 && !is_null($limit)) {
            $tags = array_slice($tags, $offset, $limit);
        }

        $tag = new ArrayWrapper($tags);

        return self::cacheSet($ckey, $str);
    }

    /**
     * get people tags from the cache
     *
     * @param string $ckey  cache key
     * @param integer $offset   offset for slicing
     * @param integer $limit    limit
     *
     * @return Profile_list results
     */

    static function getCached($ckey, $offset=0, $limit=null) {

        $keys_str = self::cacheGet($ckey);
        if ($keys_str === false) {
            return false;
        }

        $pairs = explode(';', $keys_str);
        $keys = array();
        foreach ($pairs as $pair) {
            $keys[] = explode(':', $pair);
        }

        if ($offset>=0 && !is_null($limit)) {
            $keys = array_slice($keys, $offset, $limit);
        }
        return self::getByKeys($keys);
    }

    /**
     * get Profile_list objects from the database
     * given their (tag, tagger) key pairs.
     *
     * @param array $keys   array of array(tagger, tag)
     *
     * @return Profile_list results
     */

    static function getByKeys(array $keys) {
        $cache = Cache::instance();

        if (!empty($cache)) {
            $tags = array();

            foreach ($keys as $key) {
                $t = Profile_list::getByTaggerAndTag($key[0], $key[1]);
                if (!empty($t)) {
                    $tags[] = $t;
                }
            }
            return new ArrayWrapper($tags);
        } else {
            $tag = new Profile_list();
            if (empty($keys)) {
                //if no IDs requested, just return the tag object
                return $tag;
            }

            $pairs = array();
            foreach ($keys as $key) {
                $pairs[] = '(' . $key[0] . ', "' . $key[1] . '")';
            }

            $tag->whereAdd('(tagger, tag) in (' . implode(', ', $pairs) . ')');

            $tag->find();

            $temp = array();

            while ($tag->fetch()) {
                $temp[$tag->tagger.'-'.$tag->tag] = clone($tag);
            }

            $wrapped = array();

            foreach ($keys as $key) {
                $id = $key[0].'-'.$key[1];
                if (array_key_exists($id, $temp)) {
                    $wrapped[] = $temp[$id];
                }
            }

            return new ArrayWrapper($wrapped);
        }
    }

    function insert()
    {
        $result = parent::insert();
        if ($result) {
            self::blow('profile:lists:%d', $this->tagger);
        }
        return $result;
    }
}

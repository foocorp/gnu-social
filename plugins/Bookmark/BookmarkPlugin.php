<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to enable social-bookmarking functionality
 *
 * PHP version 5
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
 *
 * @category  SocialBookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Bookmark plugin main class
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class BookmarkPlugin extends MicroAppPlugin
{
    const VERSION         = '0.1';
    const IMPORTDELICIOUS = 'BookmarkPlugin:IMPORTDELICIOUS';

    /**
     * Authorization for importing delicious bookmarks
     *
     * By default, everyone can import bookmarks except silenced people.
     *
     * @param Profile $profile Person whose rights to check
     * @param string  $right   Right to check; const value
     * @param boolean &$result Result of the check, writeable
     *
     * @return boolean hook value
     */
    function onUserRightsCheck($profile, $right, &$result)
    {
        if ($right == self::IMPORTDELICIOUS) {
            $result = !$profile->isSilenced();
            return false;
        }
        return true;
    }

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles

        $schema->ensureTable('bookmark',
                             array(new ColumnDef('id',
                                                 'char',
                                                 36,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('profile_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('url',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('title',
                                                 'varchar',
                                                 255),
                                   new ColumnDef('description',
                                                 'text'),
                                   new ColumnDef('uri',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'UNI'),
                                   new ColumnDef('created',
                                                 'datetime',
                                                 null,
                                                 false,
                                                 'MUL')));

        return true;
    }

    /**
     * Show the CSS necessary for this plugin
     *
     * @param Action $action the action being run
     *
     * @return boolean hook value
     */
    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('bookmark.css'));
        return true;
    }

    function onEndShowScripts($action)
    {
        $action->script($this->path('js/bookmark.js'));
        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        if (common_config('singleuser', 'enabled')) {
            $nickname = User::singleUserNickname();
            $m->connect('bookmarks',
                        array('action' => 'bookmarks', 'nickname' => $nickname));
            $m->connect('bookmarks/rss',
                        array('action' => 'bookmarksrss', 'nickname' => $nickname));
        } else {
            $m->connect(':nickname/bookmarks',
                        array('action' => 'bookmarks'),
                        array('nickname' => Nickname::DISPLAY_FMT));
            $m->connect(':nickname/bookmarks/rss',
                        array('action' => 'bookmarksrss'),
                        array('nickname' => Nickname::DISPLAY_FMT));
        }

        $m->connect('api/bookmarks/:id.:format',
                    array('action' => 'ApiTimelineBookmarks',
                          'id' => Nickname::INPUT_FMT,
                          'format' => '(xml|json|rss|atom|as)'));

        $m->connect('main/bookmark/new',
                    array('action' => 'newbookmark'),
                    array('id' => '[0-9]+'));

        $m->connect('main/bookmark/popup',
                    array('action' => 'bookmarkpopup'));

        $m->connect('main/bookmark/import',
                    array('action' => 'importdelicious'));

        $m->connect('main/bookmark/forurl',
                    array('action' => 'bookmarkforurl'));

        $m->connect('bookmark/:id',
                    array('action' => 'showbookmark'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('notice/by-url/:id',
                    array('action' => 'noticebyurl'),
                    array('id' => '[0-9]+'));

        return true;
    }


    /**
     * Add our two queue handlers to the queue manager
     *
     * @param QueueManager $qm current queue manager
     *
     * @return boolean hook value
     */
    function onEndInitializeQueueManager($qm)
    {
        $qm->connect('dlcsback', 'DeliciousBackupImporter');
        $qm->connect('dlcsbkmk', 'DeliciousBookmarkImporter');
        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version data
     *
     * @return value
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Bookmark',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou, Stephane Berube, Jean Baptiste Favre',
                            'homepage' => 'http://status.net/wiki/Plugin:Bookmark',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Simple extension for supporting bookmarks. ') .
                            'BookmarkList feature has been developped by Stephane Berube. ' .
                            'Integration has been done by Jean Baptiste Favre.');
        return true;
    }

    /**
     * Load our document if requested
     *
     * @param string &$title  Title to fetch
     * @param string &$output HTML to output
     *
     * @return boolean hook value
     */
    function onStartLoadDoc(&$title, &$output)
    {
        if ($title == 'bookmarklet') {
            $filename = INSTALLDIR.'/plugins/Bookmark/bookmarklet';

            $c      = file_get_contents($filename);
            $output = common_markup_to_html($c);
            return false; // success!
        }

        return true;
    }

    /**
     * Show a link to our delicious import page on profile settings form
     *
     * @param Action $action Profile settings action being shown
     *
     * @return boolean hook value
     */
    function onEndProfileSettingsActions($action)
    {
        $user = common_current_user();

        if (!empty($user) && $user->hasRight(self::IMPORTDELICIOUS)) {
            $action->elementStart('li');
            $action->element('a',
                             array('href' => common_local_url('importdelicious')),
                             // TRANS: Link text in proile leading to import form.
                             _m('Import del.icio.us bookmarks'));
            $action->elementEnd('li');
        }

        return true;
    }

    /**
     * Output our CSS class for bookmark notice list elements
     *
     * @param NoticeListItem $nli The item being shown
     *
     * @return boolean hook value
     */

    function onStartOpenNoticeListItemElement($nli)
    {
    	if (!$this->isMyNotice($nli->notice)) {
    		return true;
    	}
    	
        $nb = Bookmark::getByNotice($nli->notice);
        
        if (empty($nb)) {
        	$this->log(LOG_INFO, "Notice {$nli->notice->id} has bookmark class but no matching Bookmark record.");
        	return true;
        }
	        
	    $id = (empty($nli->repeat)) ? $nli->notice->id : $nli->repeat->id;
	    $class = 'hentry notice bookmark';
	    if ($nli->notice->scope != 0 && $nli->notice->scope != 1) {
	    	$class .= ' limited-scope';
	    }
	    $nli->out->elementStart('li', array('class' => $class,
	                                        'id' => 'notice-' . $id));
	                                        
	    Event::handle('EndOpenNoticeListItemElement', array($nli));
	    return false;
    }

    /**
     * Modify the default menu to link to our custom action
     *
     * Using event handlers, it's possible to modify the default UI for pages
     * almost without limit. In this method, we add a menu item to the default
     * primary menu for the interface to link to our action.
     *
     * The Action class provides a rich set of events to hook, as well as output
     * methods.
     *
     * @param Action $action The current action handler. Use this to
     * do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */
    function onEndPersonalGroupNav($action)
    {
        $this->user = common_current_user();

        if (!$this->user) {
            // TRANS: Client error displayed when trying to display bookmarks for a non-existing user.
            $this->clientError(_('No such user.'));
            return false;
        }

        $action->menuItem(common_local_url('bookmarks', array('nickname' => $this->user->nickname)),
                          // TRANS: Menu item in sample plugin.
                          _m('Bookmarks'),
                          // TRANS: Menu item title in sample plugin.
                          _m('A list of your bookmarks'), false, 'nav_timeline_bookmarks');
        return true;
    }

    /**
     * Save a remote bookmark (from Salmon or PuSH)
     *
     * @param Ostatus_profile $author   Author of the bookmark
     * @param Activity        $activity Activity to save
     *
     * @return Notice resulting notice.
     */
    static private function _postRemoteBookmark(Ostatus_profile $author,
                                                Activity $activity)
    {
        $bookmark = $activity->objects[0];

        $options = array('uri' => $bookmark->id,
                         'url' => $bookmark->link,
                         'is_local' => Notice::REMOTE,
                         'source' => 'ostatus');

        return self::_postBookmark($author->localProfile(), $activity, $options);
    }

    /**
     * Test if an activity represents posting a bookmark
     *
     * @param Activity $activity Activity to test
     *
     * @return true if it's a Post of a Bookmark, else false
     */
    static private function _isPostBookmark($activity)
    {
        return ($activity->verb == ActivityVerb::POST &&
                $activity->objects[0]->type == ActivityObject::BOOKMARK);
    }

    function types()
    {
        return array(ActivityObject::BOOKMARK);
    }

    /**
     * When a notice is deleted, delete the related Bookmark
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */
    function deleteRelated($notice)
    {
    	if ($this->isMyNotice($notice)) {
    		
        	$nb = Bookmark::getByNotice($notice);

        	if (!empty($nb)) {
            	$nb->delete();
        	}
    	}
    	
        return true;
    }

    /**
     * Save a bookmark from an activity
     *
     * @param Activity $activity Activity to save
     * @param Profile  $profile  Profile to use as author
     * @param array    $options  Options to pass to bookmark-saving code
     *
     * @return Notice resulting notice
     */
    function saveNoticeFromActivity($activity, $profile, $options=array())
    {
        $bookmark = $activity->objects[0];

        $relLinkEls = ActivityUtils::getLinks($bookmark->element, 'related');

        if (count($relLinkEls) < 1) {
            // TRANS: Client exception thrown when a bookmark is formatted incorrectly.
            throw new ClientException(_m('Expected exactly 1 link '.
                                        'rel=related in a Bookmark.'));
        }

        if (count($relLinkEls) > 1) {
            common_log(LOG_WARNING,
                       "Got too many link rel=related in a Bookmark.");
        }

        $linkEl = $relLinkEls[0];

        $url = $linkEl->getAttribute('href');

        $tags = array();

        foreach ($activity->categories as $category) {
            $tags[] = common_canonical_tag($category->term);
        }

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

        // Fill in location if available

        $location = $activity->context->location;

        if ($location) {
            $options['lat'] = $location->lat;
            $options['lon'] = $location->lon;
            if ($location->location_id) {
                $options['location_ns'] = $location->location_ns;
                $options['location_id'] = $location->location_id;
            }
        }

        $replies = $activity->context->attention;

        $options['groups']  = array();
        $options['replies'] = array();

        foreach ($replies as $replyURI) {
            $other = Profile::fromURI($replyURI);
            if (!empty($other)) {
                $options['replies'][] = $replyURI;
            } else {
                $group = User_group::getKV('uri', $replyURI);
                if (!empty($group)) {
                    $options['groups'][] = $replyURI;
                }
            }
        }

        // Maintain direct reply associations
        // @fixme what about conversation ID?

        if (!empty($activity->context->replyToID)) {
            $orig = Notice::getKV('uri',
                                      $activity->context->replyToID);
            if (!empty($orig)) {
                $options['reply_to'] = $orig->id;
            }
        }

        return Bookmark::saveNew($profile,
                                 $bookmark->title,
                                 $url,
                                 $tags,
                                 $bookmark->summary,
                                 $options);
    }

    function activityObjectFromNotice($notice)
    {
        assert($this->isMyNotice($notice));

        common_log(LOG_INFO,
                   "Formatting notice {$notice->uri} as a bookmark.");

        $object = new ActivityObject();
        $nb = Bookmark::getByNotice($notice);

        $object->id      = $notice->uri;
        $object->type    = ActivityObject::BOOKMARK;
        $object->title   = $nb->title;
        $object->summary = $nb->description;
        $object->link    = $notice->bestUrl();

        // Attributes of the URL

        $attachments = $notice->attachments();

        if (count($attachments) != 1) {
            // TRANS: Server exception thrown when a bookmark has multiple attachments.
            throw new ServerException(_m('Bookmark notice with the '.
                                        'wrong number of attachments.'));
        }

        $target = $attachments[0];

        $attrs = array('rel' => 'related',
                       'href' => $target->url);

        if (!empty($target->title)) {
            $attrs['title'] = $target->title;
        }

        $object->extra[] = array('link', $attrs, null);

        // Attributes of the thumbnail, if any

        $thumbnail = $target->getThumbnail();

        if (!empty($thumbnail)) {
            $tattrs = array('rel' => 'preview',
                            'href' => $thumbnail->url);

            if (!empty($thumbnail->width)) {
                $tattrs['media:width'] = $thumbnail->width;
            }

            if (!empty($thumbnail->height)) {
                $tattrs['media:height'] = $thumbnail->height;
            }

            $object->extra[] = array('link', $attrs, null);
        }

        return $object;
    }

    /**
     * Given a notice list item, returns an adapter specific
     * to this plugin.
     *
     * @param NoticeListItem $nli item to adapt
     *
     * @return NoticeListItemAdapter adapter or null
     */
    function adaptNoticeListItem($nli)
    {
        return new BookmarkListItem($nli);
    }

    function entryForm($out)
    {
        return new InitialBookmarkForm($out);
    }

    function tag()
    {
        return 'bookmark';
    }

    function appTitle()
    {
        // TRANS: Application title.
        return _m('TITLE','Bookmark');
    }

    function onEndUpgrade()
    {
        // Version 0.9.x of the plugin didn't stamp notices
        // with verb and object-type (for obvious reasons). Update
        // those notices here.

        $notice = new Notice();
        
        $notice->whereAdd('exists (select uri from bookmark where bookmark.uri = notice.uri)');
        $notice->whereAdd('((object_type is null) or (object_type = "' .ActivityObject::NOTE.'"))');

        $notice->find();

        while ($notice->fetch()) {
            $original = clone($notice);
            $notice->verb        = ActivityVerb::POST;
            $notice->object_type = ActivityObject::BOOKMARK;
            $notice->update($original);
        }
    }

    public function activityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        assert($obj->type == ActivityObject::BOOKMARK);

        $bm = Bookmark::getKV('uri', $obj->id);

        if (empty($bm)) {
            throw new ServerException("Unknown bookmark: " . $obj->id);
        }

        $out['displayName'] = $bm->title;
        $out['targetUrl']   = $bm->url;

        return true;
    }
}

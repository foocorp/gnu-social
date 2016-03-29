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

if (!defined('GNUSOCIAL')) { exit(1); }

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

        $schema->ensureTable('bookmark', Bookmark::schemaDef());

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
        $action->cssLink($this->path('css/bookmark.css'));
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
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
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
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Bookmark',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou, Stephane Berube, Jean Baptiste Favre, Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/social',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Plugin for posting bookmarks. ') .
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
    function onEndPersonalGroupNav(Menu $menu, Profile $target, Profile $scoped=null)
    {
        $menu->menuItem(common_local_url('bookmarks', array('nickname' => $target->getNickname())),
                          // TRANS: Menu item in sample plugin.
                          _m('Bookmarks'),
                          // TRANS: Menu item title in sample plugin.
                          _m('A list of your bookmarks'), false, 'nav_timeline_bookmarks');
        return true;
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
    function deleteRelated(Notice $notice)
    {
        try {
            $nb = Bookmark::fromStored($notice);
        } catch (NoResultException $e) {
            throw new AlreadyFulfilledException('Bookmark already gone when deleting: '.$e->getMessage());
        }
        $nb->delete();
    	
        return true;
    }

    /**
     * Save a bookmark from an activity
     *
     * @param Activity $activity Activity to save
     * @param Profile  $actor    Profile to use as author
     * @param array    $options  Options to pass to bookmark-saving code
     *
     * @return Notice resulting notice
     */
    protected function saveObjectFromActivity(Activity $activity, Notice $stored, array $options=array())
    {
        return Bookmark::saveActivityObject($activity->objects[0], $stored);
    }

    public function onEndNoticeAsActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        if (!$this->isMyNotice($stored)) {
            return true;
        }

        $this->extendActivity($stored, $act, $scoped);
        return false;
    }

    public function extendActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        /*$hashtags = array();
        $taglinks = array();

        foreach ($tags as $tag) {
            $hashtags[] = '#'.$tag;
            $attrs      = array('href' => Notice_tag::url($tag),
                                'rel'  => $tag,
                                'class' => 'tag');
            $taglinks[] = XMLStringer::estring('a', $attrs, $tag);
        }*/
    }

    function activityObjectFromNotice(Notice $notice)
    {
        return Bookmark::fromStored($notice)->asActivityObject();
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
        printfnq('Making sure Bookmark notices have correct verb and object_type...');

        // Version 0.9.x of the plugin didn't stamp notices
        // with verb and object-type (for obvious reasons). Update
        // those notices here.

        $notice = new Notice();
        
        $notice->joinAdd(array('uri', 'bookmark:uri'));
        $notice->whereAdd('object_type IS NULL OR object_type = '.$notice->_quote(ActivityObject::NOTE));

        $notice->find();

        while ($notice->fetch()) {
            $original = clone($notice);
            $notice->verb        = ActivityVerb::POST;
            $notice->object_type = ActivityObject::BOOKMARK;
            $notice->update($original);
        }

        printfnq("DONE.\n");
    }

    public function activityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        assert($obj->type == ActivityObject::BOOKMARK);

        $bm = Bookmark::getByPK(array('uri' => $obj->id));

        $out['displayName'] = $bm->getTitle();
        $out['targetUrl']   = $bm->getUrl();

        return true;
    }

    protected function showNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        $nb = Bookmark::fromStored($stored);

        // Whether to nofollow
        $attrs = array('href' => $nb->getUrl(), 'class' => 'bookmark-title');

        $nf = common_config('nofollow', 'external');

        if ($nf == 'never' || ($nf == 'sometimes' and $out instanceof ShowstreamAction)) {
            $attrs['rel'] = 'external';
        } else {
            $attrs['rel'] = 'nofollow external';
        }

        $out->elementStart('h3');
        $out->element('a', $attrs, $nb->getTitle());
        $out->elementEnd('h3');

        // Replies look like "for:" tags
        $replies = $stored->getReplies();
        $tags = $stored->getTags();

        if (!empty($nb->description)) {
            $out->element('p',
                          array('class' => 'bookmark-description'),
                          $nb->description);
        }

        if (!empty($replies) || !empty($tags)) {

            $out->elementStart('ul', array('class' => 'bookmark-tags'));

            foreach ($replies as $reply) {
                $other = Profile::getByPK($reply);
                $out->elementStart('li');
                $out->element('a', array('rel' => 'tag',
                                         'href' => $other->getUrl(),
                                         'title' => $other->getBestName()),
                              sprintf('for:%s', $other->getNickname()));
                $out->elementEnd('li');
                $out->text(' ');
            }

            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $out->elementStart('li');
                    $out->element('a',
                                  array('rel' => 'tag',
                                        'href' => Notice_tag::url($tag)),
                                  $tag);
                    $out->elementEnd('li');
                    $out->text(' ');
                }
            }

            $out->elementEnd('ul');
        }

    }
}

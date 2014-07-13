<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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
 * @package     Activity
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class FavoritePlugin extends ActivityHandlerPlugin
{
    protected $notify_email_fave = 1;

    public function tag()
    {
        return 'favorite';
    }

    public function types()
    {
        return array();
    }

    public function verbs()
    {
        return array(ActivityVerb::FAVORITE);
    }
    
    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('fave', Fave::schemaDef());
        return true;
    }

    public function onStartUpgrade()
    {
        // This is a migration feature that will make sure we move
        // certain User preferences to the Profile_prefs table.
        // Introduced after commit b5fd2a048fc621ea05d756caba17275ab3dd0af4
        // on Sun Jul 13 16:30:37 2014 +0200
        $user = new User();
        $user->whereAdd('emailnotifyfav IS NOT NULL');
        if ($user->find()) {
            printfnq("Detected old User table (emailnotifyfav IS NOT NULL). Moving 'emailnotifyfav' property to Profile_prefs...");
            // Make sure we have our own tables setup properly
            while ($user->fetch()) {
                $user->setPref('email', 'notify_fave', $user->emailnotifyfav);
                $orig = clone($user);
                $user->emailnotifyfav = 'null';   // flag this preference as migrated
                $user->update($orig);
            }
            printfnq("DONE.\n");
        }
    }
    
    public function onEndUpgrade()
    {
        printfnq("Ensuring all faves have a URI...");
    
        $fave = new Fave();
        $fave->whereAdd('uri IS NULL');
    
        if ($fave->find()) {
            while ($fave->fetch()) {
                try {
                    $fave->decache();
                    $fave->query(sprintf('UPDATE fave '.
                                         'SET uri = "%s", '.
                                         '    modified = "%s" '.
                                         'WHERE user_id = %d '.
                                         'AND notice_id = %d',
                                         Fave::newUri($fave->user_id, $fave->notice_id, $fave->modified),
                                         common_sql_date(strtotime($fave->modified)),
                                         $fave->user_id,
                                         $fave->notice_id));
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Error updating fave URI: " . $e->getMessage());
                }
            }
        }
    
        printfnq("DONE.\n");
    }

    public function onRouterInitialized(URLMapper $m)
    {
        // Web UI actions
        $m->connect('main/favor', array('action' => 'favor'));
        $m->connect('main/disfavor', array('action' => 'disfavor'));

        if (common_config('singleuser', 'enabled')) {
            $nickname = User::singleUserNickname();

            $m->connect('favorites',
                        array('action' => 'showfavorites',
                              'nickname' => $nickname));
            $m->connect('favoritesrss',
                        array('action' => 'favoritesrss',
                              'nickname' => $nickname));
        } else {
            $m->connect('favoritedrss', array('action' => 'favoritedrss'));
            $m->connect('favorited/', array('action' => 'favorited'));
            $m->connect('favorited', array('action' => 'favorited'));

            $m->connect(':nickname/favorites',
                        array('action' => 'showfavorites'),
                        array('nickname' => Nickname::DISPLAY_FMT));
            $m->connect(':nickname/favorites/rss',
                        array('action' => 'favoritesrss'),
                        array('nickname' => Nickname::DISPLAY_FMT));
        }

        // Favorites for API
        $m->connect('api/favorites/create.:format',
                    array('action' => 'ApiFavoriteCreate',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/destroy.:format',
                    array('action' => 'ApiFavoriteDestroy',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/list.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites/:id.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'id' => Nickname::INPUT_FMT,
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites/create/:id.:format',
                    array('action' => 'ApiFavoriteCreate',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/destroy/:id.:format',
                    array('action' => 'ApiFavoriteDestroy',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));

        // AtomPub API
        $m->connect('api/statusnet/app/favorites/:profile/:notice.atom',
                    array('action' => 'AtomPubShowFavorite'),
                    array('profile' => '[0-9]+',
                          'notice' => '[0-9]+'));

        $m->connect('api/statusnet/app/favorites/:profile.atom',
                    array('action' => 'AtomPubFavoriteFeed'),
                    array('profile' => '[0-9]+'));

        // Required for qvitter API
        $m->connect('api/statuses/favs/:id.:format',
                    array('action' => 'ApiStatusesFavs',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));
    }

    // FIXME: Set this to abstract public in lib/activityhandlerplugin.php ddwhen all plugins have migrated!
    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())  
    {
        assert($this->isMyActivity($act));

        // If empty, we should've created it ourselves on our node.
        if (!isset($options['created'])) {
            $options['created'] = !empty($act->time) ? common_sql_date($act->time) : common_sql_now();
        }
        if (!isset($options['uri'])) {
            $options['uri'] = !empty($act->id) ? $act->id : $act->selfLink;
        }

        // We must have an objects[0] here because in isMyActivity we require the count to be == 1
        $actobj = $act->objects[0];

        try {
            $object = Fave::saveActivityObject($actobj, $stored);
        } catch (ServerException $e) {
            // Probably that the favored notice doesn't exist in our local database
            // but may also be some missing profile or so, which we could catch in a
            // more explicit catch-statement.
            return null;
        }
        return $object;
    }


    public function activityObjectFromNotice(Notice $notice)
    {
        $fave = Fave::fromStored($notice);
        return $fave->asActivityObject();
    }

    public function deleteRelated(Notice $notice)
    {
        try {
            $fave = Fave::fromStored($notice);
            $fave->delete();
        } catch (NoResultException $e) {
            // Cool, no problem. We wanted to get rid of it anyway.
        }
    }

    protected function notifyMentioned(Notice $stored, array &$mentioned_ids)
    {
        require_once INSTALLDIR.'/lib/mail.php';

        foreach ($mentioned_ids as $id) {
            $mentioned = User::getKV('id', $id);
            if ($mentioned instanceof User && $mentioned->id != $stored->profile_id
                    && $mentioned->email && $mentioned->getPref('email', 'notify_fave', $this->notify_email_fave)) {   // do we have an email, and does user want it?
                mail_notify_fave($mentioned, $stored->getProfile(), $stored->getParent());
            }
        }
    }

    // API stuff

    /**
     * Typically just used to fill out Twitter-compatible API status data.
     *
     * FIXME: Make all the calls before this end up with a Notice instead of ArrayWrapper please...
     */
    public function onNoticeSimpleStatusArray($notice, array &$status, Profile $scoped=null, array $args=array())
    {
        if ($scoped instanceof Profile) {
            $status['favorited'] = Fave::existsForProfile($notice, $scoped);
        } else {
            $status['favorited'] = false;
        }
        return true;
    }

    public function onTwitterUserArray(Profile $profile, array &$userdata, Profile $scoped=null, array $args=array())
    {
        $userdata['favourites_count'] = Fave::countByProfile($profile);
    }

    /**
     * Typically just used to fill out StatusNet specific data in API calls in the referenced $info array.
     */
    public function onStatusNetApiNoticeInfo(Notice $notice, array &$info, Profile $scoped=null, array $args=array())
    {
        if ($scoped instanceof Profile) {
            $info['favorite'] = Fave::existsForProfile($notice, $scoped) ? 'true' : 'false';
        }
        return true;
    }
    
    public function onNoticeDeleteRelated(Notice $notice)
    {
        parent::onNoticeDeleteRelated($notice);

        // The below algorithm is because we want to delete fave
        // activities on any notice which _has_ faves, and not as
        // in the parent function only ones that _are_ faves.

        $fave = new Fave();
        $fave->notice_id = $notice->id;

        if ($fave->find()) {
            while ($fave->fetch()) {
                $fave->delete();
            }
        }

        $fave->free();
    }

    public function onUserDeleteRelated(User $user, array &$related)
    {
        $fave = new Fave();
        $fave->user_id = $user->id;
        $fave->delete();    // Will perform a DELETE matching "user_id = {$user->id}"

        Fave::blowCacheForProfileId($user->id);
        return true;
    }

    public function onStartNoticeListPrefill(array &$notices, array $notice_ids, Profile $scoped=null)
    {
        // prefill array of objects, before pluginfication it was Notice::fillFaves($notices)
        Fave::fillFaves($notice_ids);

        // DB caching
        if ($scoped instanceof Profile) {
            Fave::pivotGet('notice_id', $notice_ids, array('user_id' => $scoped->id));
        }
    }

    /**
     * show the "favorite" form in the notice options element
     * FIXME: Don't let a NoticeListItemAdapter slip in here (or extend that from NoticeListItem)
     *
     * @return void
     */
    public function onStartShowNoticeOptionItems($nli)
    {
        if (Event::handle('StartShowFaveForm', array($nli))) {
            $scoped = Profile::current();
            if ($scoped instanceof Profile) {
                if (Fave::existsForProfile($nli->notice, $scoped)) {
                    $disfavor = new DisfavorForm($nli->out, $nli->notice);
                    $disfavor->show();
                } else {
                    $favor = new FavorForm($nli->out, $nli->notice);
                    $favor->show();
                }
            }
            Event::handle('EndShowFaveForm', array($nli));
        }
    }

    public function showNoticeListItem(NoticeListItem $nli)
    {
        // pass
    }
    public function openNoticeListItemElement(NoticeListItem $nli)
    {
        // pass
    }
    public function closeNoticeListItemElement(NoticeListItem $nli)
    {
        // pass
    }

    public function onAppendUserActivityStreamObjects(UserActivityStream $uas, array &$objs)
    {
        $faves = array();
        $fave = new Fave();
        $fave->user_id = $uas->user->id;

        if (!empty($uas->after)) {
            $fave->whereAdd("modified > '" . common_sql_date($uas->after) . "'");
        }

        if ($fave->find()) {
            while ($fave->fetch()) {
                $faves[] = clone($fave);
            }
        }

        return $faves;
    }

    public function onStartShowThreadedNoticeTailItems(NoticeListItem $nli, Notice $notice, &$threadActive)
    {
        if ($nli instanceof ThreadedNoticeListSubItem) {
            // The sub-items are replies to a conversation, thus we use different HTML elements etc.
            $item = new ThreadedNoticeListInlineFavesItem($notice, $nli->out);
        } else {
            $item = new ThreadedNoticeListFavesItem($notice, $nli->out);
        }
        $threadActive = $item->show() || $threadActive;
        return true;
    }

    /**
     * EndInterpretCommand for FavoritePlugin will handle the 'fav' command
     * using the class FavCommand.
     *
     * @param string  $cmd     Command being run
     * @param string  $arg     Rest of the message (including address)
     * @param User    $user    User sending the message
     * @param Command &$result The resulting command object to be run.
     *
     * @return boolean hook value
     */
    public function onStartInterpretCommand($cmd, $arg, $user, &$result)
    {
        if ($result === false && $cmd == 'fav') {
            if (empty($arg)) {
                $result = null;
            } else {
                list($other, $extra) = $this->split_arg($arg);
                if (!empty($extra)) {
                    $result = null;
                } else {
                    $result = new FavCommand($user, $other);
                }
            }
            return false;
        }
        return true;
    }

    public function onHelpCommandMessages(HelpCommand $help, array &$commands)
    {
        // TRANS: Help message for IM/SMS command "fav <nickname>".
        $commands['fav <nickname>'] = _m('COMMANDHELP', "add user's last notice as a 'fave'");
        // TRANS: Help message for IM/SMS command "fav #<notice_id>".
        $commands['fav #<notice_id>'] = _m('COMMANDHELP', "add notice with the given id as a 'fave'");
    }

    /**
     * Are we allowed to perform a certain command over the API?
     */
    public function onCommandSupportedAPI(Command $cmd, array &$supported)
    {
        $supported = $supported || $cmd instanceof FavCommand;
    }

    // Form stuff (settings etc.)

    public function onEndEmailFormData(Action $action, Profile $scoped)
    {
        // getConfigData will fall back on systemwide default
        // and we only wish to save numerical true or false.
        $emailfave = $scoped->getPref('email', 'notify_fave', $this->notify_email_fave) ? 1 : 0;

        $action->elementStart('li');
        $action->checkbox('email-notify_fave',
                        // TRANS: Checkbox label in e-mail preferences form.
                        _('Send me email when someone adds my notice as a favorite.'),
                        $emailfave);
        $action->elementEnd('li');

        return true;
    }

    public function onStartEmailSaveForm(Action $action, Profile $scoped)
    {
        $emailfave = $action->boolean('email-notify_fave') ? 1 : 0;
        try {
            if ($emailfave == $scoped->getPref('email', 'notify_fave')) {
                // No need to update setting
                return true;
            }
        } catch (NoResultException $e) {
            // Apparently there's no previously stored setting, then continue to save it as it is now.
        }

        $scoped->setPref('email', 'notify_fave', $emailfave);

        return true;
    }

    // Layout stuff

    public function onEndPersonalGroupNav(Menu $menu, Profile $target, Profile $scoped=null)
    {
        $menu->out->menuItem(common_local_url('showfavorites', array('nickname' => $target->getNickname())),
                             // TRANS: Menu item in personal group navigation menu.
                             _m('MENU','Favorites'),
                             // @todo i18n FIXME: Need to make this two messages.
                             // TRANS: Menu item title in personal group navigation menu.
                             // TRANS: %s is a username.
                             sprintf(_('%s\'s favorite notices'), $target->getBestName()),
                             $scoped instanceof Profile && $target->id === $scoped->id && $menu->actionName =='showfavorites',
                            'nav_timeline_favorites');
    }

    public function onEndPublicGroupNav(Menu $menu)
    {
        if (!common_config('singleuser', 'enabled')) {
            // TRANS: Menu item in search group navigation panel.
            $menu->out->menuItem(common_local_url('favorited'), _m('MENU','Popular'),
                                 // TRANS: Menu item title in search group navigation panel.
                                 _('Popular notices'), $menu->actionName == 'favorited', 'nav_timeline_favorited');
        }
    }

    public function onEndShowSections(Action $action)
    {
        if (!$action->isAction(array('all', 'public'))) {
            return true;
        }

        if (!common_config('performance', 'high')) {
            $section = new PopularNoticeSection($action, $action->getScoped());
            $section->show();
        }
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Favorite',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Favorites (likes) using ActivityStreams.'));

        return true;
    }
}

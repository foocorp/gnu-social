<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do "real time" updates of timelines using Ajax
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Superclass for plugin to do realtime updates
 *
 * Based on experience with the Comet and Meteor plugins,
 * this superclass extracts out some of the common functionality
 *
 * Currently depends on Favorite plugin.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RealtimePlugin extends Plugin
{
    protected $showurl = null;

    /**
     * When it's time to initialize the plugin, calculate and
     * pass the URLs we need.
     */
    function onInitializePlugin()
    {
        // FIXME: need to find a better way to pass this pattern in
        $this->showurl = common_local_url('shownotice',
                                            array('notice' => '0000000000'));
        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('realtime_channel', Realtime_channel::schemaDef());
        return true;
    }

    /**
     * Hook for RouterInitialized event.
     *
     * @param URLMapper $m path-to-action mapper
     * @return boolean hook return
     */
    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('main/channel/:channelkey/keepalive',
                    array('action' => 'keepalivechannel'),
                    array('channelkey' => '[a-z0-9]{32}'));
        $m->connect('main/channel/:channelkey/close',
                    array('action' => 'closechannel'),
                    array('channelkey' => '[a-z0-9]{32}'));
        return true;
    }

    function onEndShowScripts($action)
    {
        $channel = $this->_getChannel($action);

        if (empty($channel)) {
            return true;
        }

        $timeline = $this->_pathToChannel(array($channel->channel_key));

        // If there's not a timeline on this page,
        // just return true

        if (empty($timeline)) {
            return true;
        }

        $base = $action->selfUrl();
        if (mb_strstr($base, '?')) {
            $url = $base . '&realtime=1';
        } else {
            $url = $base . '?realtime=1';
        }

        $scripts = $this->_getScripts();

        foreach ($scripts as $script) {
            $action->script($script);
        }

        $user = common_current_user();

        if (!empty($user->id)) {
            $user_id = $user->id;
        } else {
            $user_id = 0;
        }

        if ($action->boolean('realtime')) {
            $realtimeUI = ' RealtimeUpdate.initPopupWindow();';
        }
        else {
            $pluginPath = common_path('plugins/Realtime/');
            $keepalive = common_local_url('keepalivechannel', array('channelkey' => $channel->channel_key));
            $close = common_local_url('closechannel', array('channelkey' => $channel->channel_key));
            $realtimeUI = ' RealtimeUpdate.initActions('.json_encode($url).', '.json_encode($timeline).', '.json_encode($pluginPath).', '.json_encode($keepalive).', '.json_encode($close).'); ';
        }

        $script = ' $(document).ready(function() { '.
          $realtimeUI.
            $this->_updateInitialize($timeline, $user_id).
          '}); ';
        $action->inlineScript($script);

        return true;
    }

    public function onEndShowStylesheets(Action $action)
    {
        $urlpath = self::staticPath(str_replace('Plugin','',__CLASS__),
                                    'css/realtimeupdate.css');
        $action->cssLink($urlpath, null, 'screen, projection, tv');
        return true;
    }

    public function onHandleQueuedNotice(Notice $notice)
    {
        $paths = array();

        // Add to the author's timeline

        try {
            $profile = $notice->getProfile();
        } catch (Exception $e) {
            $this->log(LOG_ERR, $e->getMessage());
            return true;
        }

        try {
            $user = $profile->getUser();
            $paths[] = array('showstream', $user->nickname, null);
        } catch (NoSuchUserException $e) {
            // We really should handle the remote profile views too
            $user = null;
        }

        // Add to the public timeline

        $is_local = intval($notice->is_local);
        if ($is_local === Notice::LOCAL_PUBLIC ||
                ($is_local === Notice::REMOTE && !common_config('public', 'localonly'))) {
            $paths[] = array('public', null, null);
        }

        // Add to the tags timeline

        $tags = $this->getNoticeTags($notice);

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $paths[] = array('tag', $tag, null);
            }
        }

        // Add to inbox timelines
        // XXX: do a join

        $ni = $notice->whoGets();

        foreach (array_keys($ni) as $user_id) {
            $user = User::getKV('id', $user_id);
            $paths[] = array('all', $user->nickname, null);
        }

        // Add to the replies timeline

        $reply = new Reply();
        $reply->notice_id = $notice->id;

        if ($reply->find()) {
            while ($reply->fetch()) {
                $user = User::getKV('id', $reply->profile_id);
                if (!empty($user)) {
                    $paths[] = array('replies', $user->nickname, null);
                }
            }
        }

        // Add to the group timeline
        // XXX: join

        $gi = new Group_inbox();
        $gi->notice_id = $notice->id;

        if ($gi->find()) {
            while ($gi->fetch()) {
                $ug = User_group::getKV('id', $gi->group_id);
                $paths[] = array('showgroup', $ug->nickname, null);
            }
        }

        if (count($paths) > 0) {

            $json = $this->noticeAsJson($notice);

            $this->_connect();

            // XXX: We should probably fan-out here and do a
            // new queue item for each path

            foreach ($paths as $path) {

                list($action, $arg1, $arg2) = $path;

                $channels = Realtime_channel::getAllChannels($action, $arg1, $arg2);
                $this->log(LOG_INFO, sprintf(_("%d candidate channels for notice %d"),
                                             count($channels), 
                                             $notice->id));

                foreach ($channels as $channel) {

                    // XXX: We should probably fan-out here and do a
                    // new queue item for each user/path combo

                    if (is_null($channel->user_id)) {
                        $profile = null;
                    } else {
                        $profile = Profile::getKV('id', $channel->user_id);
                    }
                    if ($notice->inScope($profile)) {
                        $this->log(LOG_INFO, 
                                   sprintf(_("Delivering notice %d to channel (%s, %s, %s) for user '%s'"),
                                           $notice->id,
                                           $channel->action,
                                           $channel->arg1,
                                           $channel->arg2,
                                           ($profile) ? ($profile->nickname) : "<public>"));
                        $timeline = $this->_pathToChannel(array($channel->channel_key));
                        $this->_publish($timeline, $json);
                    }
                }
            }

            $this->_disconnect();
        }

        return true;
    }

    function onStartShowBody($action)
    {
        $realtime = $action->boolean('realtime');
        if (!$realtime) {
            return true;
        }

        $action->elementStart('body',
                              (common_current_user()) ? array('id' => $action->trimmed('action'),
                                                              'class' => 'user_in realtime-popup')
                              : array('id' => $action->trimmed('action'),
                                      'class'=> 'realtime-popup'));

        // XXX hack to deal with JS that tries to get the
        // root url from page output

        $action->elementStart('address');

        if (common_config('singleuser', 'enabled')) {
            $user = User::singleUser();
            $url = common_local_url('showstream', array('nickname' => $user->nickname));
        } else {
            $url = common_local_url('public');
        }

        $action->element('a', array('class' => 'url',
                                    'href' => $url),
                         '');

        $action->elementEnd('address');

        $action->showContentBlock();
        $action->showScripts();
        $action->elementEnd('body');
        return false; // No default processing
    }

    function noticeAsJson($notice)
    {
        // FIXME: this code should be abstracted to a neutral third
        // party, like Notice::asJson(). I'm not sure of the ethics
        // of refactoring from within a plugin, so I'm just abusing
        // the ApiAction method. Don't do this unless you're me!

        $act = new ApiAction('/dev/null');

        $arr = $act->twitterStatusArray($notice, true);
        $arr['url'] = $notice->getUrl(true);
        $arr['html'] = htmlspecialchars($notice->rendered);
        $arr['source'] = htmlspecialchars($arr['source']);
        $arr['conversation_url'] = $notice->getConversationUrl();

        $profile = $notice->getProfile();
        $arr['user']['profile_url'] = $profile->profileurl;

        // Add needed repeat data

        if (!empty($notice->repeat_of)) {
            $original = Notice::getKV('id', $notice->repeat_of);
            if ($original instanceof Notice) {
                $arr['retweeted_status']['url'] = $original->getUrl(true);
                $arr['retweeted_status']['html'] = htmlspecialchars($original->rendered);
                $arr['retweeted_status']['source'] = htmlspecialchars($original->source);
                $originalProfile = $original->getProfile();
                $arr['retweeted_status']['user']['profile_url'] = $originalProfile->profileurl;
                $arr['retweeted_status']['conversation_url'] = $original->getConversationUrl();
            }
            unset($original);
        }

        return $arr;
    }

    function getNoticeTags($notice)
    {
        $tags = null;

        $nt = new Notice_tag();
        $nt->notice_id = $notice->id;

        if ($nt->find()) {
            $tags = array();
            while ($nt->fetch()) {
                $tags[] = $nt->tag;
            }
        }

        $nt->free();
        $nt = null;

        return $tags;
    }

    function _getScripts()
    {
        $urlpath = self::staticPath(str_replace('Plugin','',__CLASS__),
                                    'js/realtimeupdate.js');
        return array($urlpath);
    }

    /**
     * Export any i18n messages that need to be loaded at runtime...
     *
     * @param Action $action
     * @param array $messages
     *
     * @return boolean hook return value
     */
    function onEndScriptMessages($action, &$messages)
    {
        // TRANS: Text label for realtime view "play" button, usually replaced by an icon.
        $messages['realtime_play'] = _m('BUTTON', 'Play');
        // TRANS: Tooltip for realtime view "play" button.
        $messages['realtime_play_tooltip'] = _m('TOOLTIP', 'Play');
        // TRANS: Text label for realtime view "pause" button
        $messages['realtime_pause'] = _m('BUTTON', 'Pause');
        // TRANS: Tooltip for realtime view "pause" button
        $messages['realtime_pause_tooltip'] = _m('TOOLTIP', 'Pause');
        // TRANS: Text label for realtime view "popup" button, usually replaced by an icon.
        $messages['realtime_popup'] = _m('BUTTON', 'Pop up');
        // TRANS: Tooltip for realtime view "popup" button.
        $messages['realtime_popup_tooltip'] = _m('TOOLTIP', 'Pop up in a window');

        return true;
    }

    function _updateInitialize($timeline, $user_id)
    {
        return "RealtimeUpdate.init($user_id, \"$this->showurl\"); ";
    }

    function _connect()
    {
    }

    function _publish($timeline, $json)
    {
    }

    function _disconnect()
    {
    }

    function _pathToChannel($path)
    {
        return '';
    }


    function _getTimeline($action)
    {
        $channel = $this->_getChannel($action);
        if (empty($channel)) {
            return null;
        }

        return $this->_pathToChannel(array($channel->channel_key));
    }

    function _getChannel($action)
    {
        $timeline = null;
        $arg1     = null;
        $arg2     = null;

        $action_name = $action->trimmed('action');

        // FIXME: lists
        // FIXME: search (!)
        // FIXME: profile + tag

        switch ($action_name) {
         case 'public':
            // no arguments
            break;
         case 'tag':
            $tag = $action->trimmed('tag');
            if (!empty($tag)) {
                $arg1 = $tag;
            } else {
                $this->log(LOG_NOTICE, "Unexpected 'tag' action without tag argument");
                return null;
            }
            break;
         case 'showstream':
         case 'all':
         case 'replies':
         case 'showgroup':
            $nickname = common_canonical_nickname($action->trimmed('nickname'));
            if (!empty($nickname)) {
                $arg1 = $nickname;
            } else {
                $this->log(LOG_NOTICE, "Unexpected $action_name action without nickname argument.");
                return null;
            }
            break;
         default:
            return null;
        }

        $user = common_current_user();

        $user_id = (!empty($user)) ? $user->id : null;

        $channel = Realtime_channel::getChannel($user_id,
                                                $action_name,
                                                $arg1,
                                                $arg2);

        return $channel;
    }

    function onStartReadWriteTables(&$alwaysRW, &$rwdb)
    {
        $alwaysRW[] = 'realtime_channel';
        return true;
    }
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/');

class FeedSubException extends Exception
{
}

class OStatusPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        // Discovery actions
        $m->connect('.well-known/host-meta',
                    array('action' => 'hostmeta'));
        $m->connect('main/xrd',
                    array('action' => 'userxrd'));
        $m->connect('main/ownerxrd',
                    array('action' => 'ownerxrd'));
        $m->connect('main/ostatus',
                    array('action' => 'ostatusinit'));
        $m->connect('main/ostatus?nickname=:nickname',
                  array('action' => 'ostatusinit'), array('nickname' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatus?group=:group',
                  array('action' => 'ostatusinit'), array('group' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'));
        $m->connect('main/ostatusgroup',
                    array('action' => 'ostatusgroup'));

        // PuSH actions
        $m->connect('main/push/hub', array('action' => 'pushhub'));

        $m->connect('main/push/callback/:feed',
                    array('action' => 'pushcallback'),
                    array('feed' => '[0-9]+'));

        // Salmon endpoint
        $m->connect('main/salmon/user/:id',
                    array('action' => 'usersalmon'),
                    array('id' => '[0-9]+'));
        $m->connect('main/salmon/group/:id',
                    array('action' => 'groupsalmon'),
                    array('id' => '[0-9]+'));
        return true;
    }

    /**
     * Set up queue handlers for outgoing hub pushes
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        // Prepare outgoing distributions after notice save.
        $qm->connect('ostatus', 'OStatusQueueHandler');

        // Outgoing from our internal PuSH hub
        $qm->connect('hubconf', 'HubConfQueueHandler');
        $qm->connect('hubprep', 'HubPrepQueueHandler');

        $qm->connect('hubout', 'HubOutQueueHandler');

        // Outgoing Salmon replies (when we don't need a return value)
        $qm->connect('salmon', 'SalmonQueueHandler');

        // Incoming from a foreign PuSH hub
        $qm->connect('pushin', 'PushInQueueHandler');
        return true;
    }

    /**
     * Put saved notices into the queue for pubsub distribution.
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        if ($notice->isLocal()) {
            // put our transport first, in case there's any conflict (like OMB)
            array_unshift($transports, 'ostatus');
        }
        return true;
    }

    /**
     * Add a link header for LRDD Discovery
     */
    function onStartShowHTML($action)
    {
        if ($action instanceof ShowstreamAction) {
            $acct = 'acct:'. $action->profile->nickname .'@'. common_config('site', 'server');
            $url = common_local_url('userxrd');
            $url.= '?uri='. $acct;

            header('Link: <'.$url.'>; rel="'. Discovery::LRDD_REL.'"; type="application/xrd+xml"');
        }
    }

    /**
     * Set up a PuSH hub link to our internal link for canonical timeline
     * Atom feeds for users and groups.
     */
    function onStartApiAtom($feed)
    {
        $id = null;

        if ($feed instanceof AtomUserNoticeFeed) {
            $salmonAction = 'usersalmon';
            $user = $feed->getUser();
            $id   = $user->id;
            $profile = $user->getProfile();
            $feed->setActivitySubject($profile->asActivityNoun('subject'));
        } else if ($feed instanceof AtomGroupNoticeFeed) {
            $salmonAction = 'groupsalmon';
            $group = $feed->getGroup();
            $id = $group->id;
            $feed->setActivitySubject($group->asActivitySubject());
        } else {
            return true;
        }

        if (!empty($id)) {
            $hub = common_config('ostatus', 'hub');
            if (empty($hub)) {
                // Updates will be handled through our internal PuSH hub.
                $hub = common_local_url('pushhub');
            }
            $feed->addLink($hub, array('rel' => 'hub'));

            // Also, we'll add in the salmon link
            $salmon = common_local_url($salmonAction, array('id' => $id));
            $feed->addLink($salmon, array('rel' => Salmon::NS_REPLIES));
            $feed->addLink($salmon, array('rel' => Salmon::NS_MENTIONS));
        }

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        $map = array('activityverb' => 'activity',
                     'activityobject' => 'activity',
                     'activityutils' => 'activity');
        if (isset($map[$lower])) {
            $lower = $map[$lower];
        }
        $files = array("$base/classes/$cls.php",
                       "$base/lib/$lower.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    /**
     * Add in an OStatus subscribe button
     */
    function onStartProfileRemoteSubscribe($output, $profile)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            // Add an OStatus subscribe
            $output->elementStart('li', 'entity_subscribe');
            $url = common_local_url('ostatusinit',
                                    array('nickname' => $profile->nickname));
            $output->element('a', array('href' => $url,
                                        'class' => 'entity_remote_subscribe'),
                                _m('Subscribe'));

            $output->elementEnd('li');
        }

        return false;
    }

    function onStartGroupSubscribe($output, $group)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            // Add an OStatus subscribe
            $url = common_local_url('ostatusinit',
                                    array('group' => $group->nickname));
            $output->element('a', array('href' => $url,
                                        'class' => 'entity_remote_subscribe'),
                                _m('Join'));
        }

        return true;
    }

    /**
     * Check if we've got remote replies to send via Salmon.
     *
     * @fixme push webfinger lookup & sending to a background queue
     * @fixme also detect short-form name for remote subscribees where not ambiguous
     */

    function onEndNoticeSave($notice)
    {
    }

    /**
     * Find any explicit remote mentions. Accepted forms:
     *   Webfinger: @user@example.com
     *   Profile link: @example.com/mublog/user
     * @param Profile $sender (os user?)
     * @param string $text input markup text
     * @param array &$mention in/out param: set of found mentions
     * @return boolean hook return value
     */

    function onEndFindMentions($sender, $text, &$mentions)
    {
        $matches = array();

        // Webfinger matches: @user@example.com
        if (preg_match_all('!(?:^|\s+)@((?:\w+\.)*\w+@(?:\w+\-?\w+\.)*\w+(?:\w+\-\w+)*\.\w+)!',
                       $text,
                       $wmatches,
                       PREG_OFFSET_CAPTURE)) {
            foreach ($wmatches[1] as $wmatch) {
                list($target, $pos) = $wmatch;
                $this->log(LOG_INFO, "Checking webfinger '$target'");
                try {
                    $oprofile = Ostatus_profile::ensureWebfinger($target);
                    if ($oprofile && !$oprofile->isGroup()) {
                        $profile = $oprofile->localProfile();
                        $matches[$pos] = array('mentioned' => array($profile),
                                               'text' => $target,
                                               'position' => $pos,
                                               'url' => $profile->profileurl);
                    }
                } catch (Exception $e) {
                    $this->log(LOG_ERR, "Webfinger check failed: " . $e->getMessage());
                }
            }
        }

        // Profile matches: @example.com/mublog/user
        if (preg_match_all('!(?:^|\s+)@((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)+)!',
                       $text,
                       $wmatches,
                       PREG_OFFSET_CAPTURE)) {
            foreach ($wmatches[1] as $wmatch) {
                list($target, $pos) = $wmatch;
                $schemes = array('http', 'https');
                foreach ($schemes as $scheme) {
                    $url = "$scheme://$target";
                    $this->log(LOG_INFO, "Checking profile address '$url'");
                    try {
                        $oprofile = Ostatus_profile::ensureProfileURL($url);
                        if ($oprofile && !$oprofile->isGroup()) {
                            $profile = $oprofile->localProfile();
                            $matches[$pos] = array('mentioned' => array($profile),
                                                   'text' => $target,
                                                   'position' => $pos,
                                                   'url' => $profile->profileurl);
                            break;
                        }
                    } catch (Exception $e) {
                        $this->log(LOG_ERR, "Profile check failed: " . $e->getMessage());
                    }
                }
            }
        }

        foreach ($mentions as $i => $other) {
            // If we share a common prefix with a local user, override it!
            $pos = $other['position'];
            if (isset($matches[$pos])) {
                $mentions[$i] = $matches[$pos];
                unset($matches[$pos]);
            }
        }
        foreach ($matches as $mention) {
            $mentions[] = $mention;
        }

        return true;
    }

    /**
     * Allow remote profile references to be used in commands:
     *   sub update@status.net
     *   whois evan@identi.ca
     *   reply http://identi.ca/evan hey what's up
     *
     * @param Command $command
     * @param string $arg
     * @param Profile &$profile
     * @return hook return code
     */
    function onStartCommandGetProfile($command, $arg, &$profile)
    {
        $oprofile = $this->pullRemoteProfile($arg);
        if ($oprofile && !$oprofile->isGroup()) {
            $profile = $oprofile->localProfile();
            return false;
        } else {
            return true;
        }
    }

    /**
     * Allow remote group references to be used in commands:
     *   join group+statusnet@identi.ca
     *   join http://identi.ca/group/statusnet
     *   drop identi.ca/group/statusnet
     *
     * @param Command $command
     * @param string $arg
     * @param User_group &$group
     * @return hook return code
     */
    function onStartCommandGetGroup($command, $arg, &$group)
    {
        $oprofile = $this->pullRemoteProfile($arg);
        if ($oprofile && $oprofile->isGroup()) {
            $group = $oprofile->localGroup();
            return false;
        } else {
            return true;
        }
    }

    protected function pullRemoteProfile($arg)
    {
        $oprofile = null;
        if (preg_match('!^((?:\w+\.)*\w+@(?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+)$!', $arg)) {
            // webfinger lookup
            try {
                return Ostatus_profile::ensureWebfinger($arg);
            } catch (Exception $e) {
                common_log(LOG_ERR, 'Webfinger lookup failed for ' .
                                    $arg . ': ' . $e->getMessage());
            }
        }

        // Look for profile URLs, with or without scheme:
        $urls = array();
        if (preg_match('!^https?://((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)+)$!', $arg)) {
            $urls[] = $arg;
        }
        if (preg_match('!^((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)+)$!', $arg)) {
            $schemes = array('http', 'https');
            foreach ($schemes as $scheme) {
                $urls[] = "$scheme://$arg";
            }
        }

        foreach ($urls as $url) {
            try {
                return Ostatus_profile::ensureProfileURL($url);
            } catch (Exception $e) {
                common_log(LOG_ERR, 'Profile lookup failed for ' .
                                    $arg . ': ' . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * Make sure necessary tables are filled out.
     */
    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('ostatus_profile', Ostatus_profile::schemaDef());
        $schema->ensureTable('ostatus_source', Ostatus_source::schemaDef());
        $schema->ensureTable('feedsub', FeedSub::schemaDef());
        $schema->ensureTable('hubsub', HubSub::schemaDef());
        $schema->ensureTable('magicsig', Magicsig::schemaDef());
        return true;
    }

    function onEndShowStatusNetStyles($action) {
        $action->cssLink('plugins/OStatus/theme/base/css/ostatus.css');
        return true;
    }

    function onEndShowStatusNetScripts($action) {
        $action->script('plugins/OStatus/js/ostatus.js');
        return true;
    }

    /**
     * Override the "from ostatus" bit in notice lists to link to the
     * original post and show the domain it came from.
     *
     * @param Notice in $notice
     * @param string out &$name
     * @param string out &$url
     * @param string out &$title
     * @return mixed hook return code
     */
    function onStartNoticeSourceLink($notice, &$name, &$url, &$title)
    {
        if ($notice->source == 'ostatus') {
            if ($notice->url) {
                $bits = parse_url($notice->url);
                $domain = $bits['host'];
                if (substr($domain, 0, 4) == 'www.') {
                    $name = substr($domain, 4);
                } else {
                    $name = $domain;
                }

                $url = $notice->url;
                $title = sprintf(_m("Sent from %s via OStatus"), $domain);
                return false;
            }
        }
	return true;
    }

    /**
     * Send incoming PuSH feeds for OStatus endpoints in for processing.
     *
     * @param FeedSub $feedsub
     * @param DOMDocument $feed
     * @return mixed hook return code
     */
    function onStartFeedSubReceive($feedsub, $feed)
    {
        $oprofile = Ostatus_profile::staticGet('feeduri', $feedsub->uri);
        if ($oprofile) {
            $oprofile->processFeed($feed, 'push');
        } else {
            common_log(LOG_DEBUG, "No ostatus profile for incoming feed $feedsub->uri");
        }
    }

    /**
     * When about to subscribe to a remote user, start a server-to-server
     * PuSH subscription if needed. If we can't establish that, abort.
     *
     * @fixme If something else aborts later, we could end up with a stray
     *        PuSH subscription. This is relatively harmless, though.
     *
     * @param Profile $subscriber
     * @param Profile $other
     *
     * @return hook return code
     *
     * @throws Exception
     */
    function onStartSubscribe($subscriber, $other)
    {
        $user = User::staticGet('id', $subscriber->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $other->id);

        if (empty($oprofile)) {
            return true;
        }

        if (!$oprofile->subscribe()) {
            throw new Exception(_m('Could not set up remote subscription.'));
        }
    }

    /**
     * Having established a remote subscription, send a notification to the
     * remote OStatus profile's endpoint.
     *
     * @param Profile $subscriber
     * @param Profile $other
     *
     * @return hook return code
     *
     * @throws Exception
     */
    function onEndSubscribe($subscriber, $other)
    {
        $user = User::staticGet('id', $subscriber->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $other->id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FOLLOW;

        $act->id   = TagURI::mint('follow:%d:%d:%s',
                                  $subscriber->id,
                                  $other->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Follow");
        $act->content = sprintf(_("%s is now following %s."),
                               $subscriber->getBestName(),
                               $other->getBestName());

        $act->actor   = ActivityObject::fromProfile($subscriber);
        $act->object  = ActivityObject::fromProfile($other);

        $oprofile->notifyActivity($act, $subscriber);

        return true;
    }

    /**
     * Notify remote server and garbage collect unused feeds on unsubscribe.
     * @fixme send these operations to background queues
     *
     * @param User $user
     * @param Profile $other
     * @return hook return value
     */
    function onEndUnsubscribe($profile, $other)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $other->id);

        if (empty($oprofile)) {
            return true;
        }

        // Drop the PuSH subscription if there are no other subscribers.
        $oprofile->garbageCollect();

        $act = new Activity();

        $act->verb = ActivityVerb::UNFOLLOW;

        $act->id   = TagURI::mint('unfollow:%d:%d:%s',
                                  $profile->id,
                                  $other->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Unfollow");
        $act->content = sprintf(_("%s stopped following %s."),
                               $profile->getBestName(),
                               $other->getBestName());

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromProfile($other);

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    /**
     * When one of our local users tries to join a remote group,
     * notify the remote server. If the notification is rejected,
     * deny the join.
     *
     * @param User_group $group
     * @param User $user
     *
     * @return mixed hook return value
     */

    function onStartJoinGroup($group, $user)
    {
        $oprofile = Ostatus_profile::staticGet('group_id', $group->id);
        if ($oprofile) {
            if (!$oprofile->subscribe()) {
                throw new Exception(_m('Could not set up remote group membership.'));
            }

            $member = Profile::staticGet($user->id);

            $act = new Activity();
            $act->id = TagURI::mint('join:%d:%d:%s',
                                    $member->id,
                                    $group->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($member);
            $act->verb = ActivityVerb::JOIN;
            $act->object = $oprofile->asActivityObject();

            $act->time = time();
            $act->title = _m("Join");
            $act->content = sprintf(_m("%s has joined group %s."),
                                    $member->getBestName(),
                                    $oprofile->getBestName());

            if ($oprofile->notifyActivity($act, $member)) {
                return true;
            } else {
                $oprofile->garbageCollect();
                throw new Exception(_m("Failed joining remote group."));
            }
        }
    }

    /**
     * When one of our local users leaves a remote group, notify the remote
     * server.
     *
     * @fixme Might be good to schedule a resend of the leave notification
     * if it failed due to a transitory error. We've canceled the local
     * membership already anyway, but if the remote server comes back up
     * it'll be left with a stray membership record.
     *
     * @param User_group $group
     * @param User $user
     *
     * @return mixed hook return value
     */

    function onEndLeaveGroup($group, $user)
    {
        $oprofile = Ostatus_profile::staticGet('group_id', $group->id);
        if ($oprofile) {
            // Drop the PuSH subscription if there are no other subscribers.
            $oprofile->garbageCollect();

            $member = Profile::staticGet($user->id);

            $act = new Activity();
            $act->id = TagURI::mint('leave:%d:%d:%s',
                                    $member->id,
                                    $group->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($member);
            $act->verb = ActivityVerb::LEAVE;
            $act->object = $oprofile->asActivityObject();

            $act->time = time();
            $act->title = _m("Leave");
            $act->content = sprintf(_m("%s has left group %s."),
                                    $member->getBestName(),
                                    $oprofile->getBestName());

            $oprofile->notifyActivity($act, $member);
        }
    }

    /**
     * Notify remote users when their notices get favorited.
     *
     * @param Profile or User $profile of local user doing the faving
     * @param Notice $notice being favored
     * @return hook return value
     */

    function onEndFavorNotice(Profile $profile, Notice $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $notice->profile_id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FAVORITE;
        $act->id   = TagURI::mint('favor:%d:%d:%s',
                                  $profile->id,
                                  $notice->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Favor");
        $act->content = sprintf(_("%s marked notice %s as a favorite."),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromNotice($notice);

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    /**
     * Notify remote users when their notices get de-favorited.
     *
     * @param Profile $profile Profile person doing the de-faving
     * @param Notice  $notice  Notice being favored
     *
     * @return hook return value
     */

    function onEndDisfavorNotice(Profile $profile, Notice $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $notice->profile_id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::UNFAVORITE;
        $act->id   = TagURI::mint('disfavor:%d:%d:%s',
                                  $profile->id,
                                  $notice->id,
                                  common_date_iso8601(time()));
        $act->time    = time();
        $act->title   = _("Disfavor");
        $act->content = sprintf(_("%s marked notice %s as no longer a favorite."),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromNotice($notice);

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    function onStartGetProfileUri($profile, &$uri)
    {
        $oprofile = Ostatus_profile::staticGet('profile_id', $profile->id);
        if (!empty($oprofile)) {
            $uri = $oprofile->uri;
            return false;
        }
        return true;
    }

    function onStartUserGroupHomeUrl($group, &$url)
    {
        return $this->onStartUserGroupPermalink($group, $url);
    }

    function onStartUserGroupPermalink($group, &$url)
    {
        $oprofile = Ostatus_profile::staticGet('group_id', $group->id);
        if ($oprofile) {
            // @fixme this should probably be in the user_group table
            // @fixme this uri not guaranteed to be a profile page
            $url = $oprofile->uri;
            return false;
        }
    }

    function onStartShowSubscriptionsContent($action)
    {
        $this->showEntityRemoteSubscribe($action);

        return true;
    }

    function onStartShowUserGroupsContent($action)
    {
        $this->showEntityRemoteSubscribe($action, 'ostatusgroup');

        return true;
    }

    function onEndShowSubscriptionsMiniList($action)
    {
        $this->showEntityRemoteSubscribe($action);

        return true;
    }

    function onEndShowGroupsMiniList($action)
    {
        $this->showEntityRemoteSubscribe($action, 'ostatusgroup');

        return true;
    }

    function showEntityRemoteSubscribe($action, $target='ostatussub')
    {
        $user = common_current_user();
        if ($user && ($user->id == $action->profile->id)) {
            $action->elementStart('div', 'entity_actions');
            $action->elementStart('p', array('id' => 'entity_remote_subscribe',
                                             'class' => 'entity_subscribe'));
            $action->element('a', array('href' => common_local_url($target),
                                        'class' => 'entity_remote_subscribe')
                                , _m('Remote'));
            $action->elementEnd('p');
            $action->elementEnd('div');
        }
    }

    /**
     * Ping remote profiles with updates to this profile.
     * Salmon pings are queued for background processing.
     */
    function onEndBroadcastProfile(Profile $profile)
    {
        $user = User::staticGet('id', $profile->id);

        // Find foreign accounts I'm subscribed to that support Salmon pings.
        //
        // @fixme we could run updates through the PuSH feed too,
        // in which case we can skip Salmon pings to folks who
        // are also subscribed to me.
        $sql = "SELECT * FROM ostatus_profile " .
               "WHERE profile_id IN " .
               "(SELECT subscribed FROM subscription WHERE subscriber=%d) " .
               "OR group_id IN " .
               "(SELECT group_id FROM group_member WHERE profile_id=%d)";
        $oprofile = new Ostatus_profile();
        $oprofile->query(sprintf($sql, $profile->id, $profile->id));

        if ($oprofile->N == 0) {
            common_log(LOG_DEBUG, "No OStatus remote subscribees for $profile->nickname");
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::UPDATE_PROFILE;
        $act->id   = TagURI::mint('update-profile:%d:%s',
                                  $profile->id,
                                  common_date_iso8601(time()));
        $act->time    = time();
        $act->title   = _m("Profile update");
        $act->content = sprintf(_m("%s has updated their profile page."),
                               $profile->getBestName());

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = $act->actor;

        while ($oprofile->fetch()) {
            $oprofile->notifyDeferred($act, $profile);
        }

        return true;
    }

    function onStartProfileListItemActionElements($item)
    {
        if (!common_logged_in()) {

            $profileUser = User::staticGet('id', $item->profile->id);

            if (!empty($profileUser)) {

                $output = $item->out;

                // Add an OStatus subscribe
                $output->elementStart('li', 'entity_subscribe');
                $url = common_local_url('ostatusinit',
                                        array('nickname' => $profileUser->nickname));
                $output->element('a', array('href' => $url,
                                            'class' => 'entity_remote_subscribe'),
                                 _m('Subscribe'));
                $output->elementEnd('li');
            }
        }

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'OStatus',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou, James Walker, Brion Vibber, Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:OStatus',
                            'rawdescription' =>
                            _m('Follow people across social networks that implement '.
                               '<a href="http://ostatus.org/">OStatus</a>.'));

        return true;
    }

    /**
     * Utility function to check if the given URL is a canonical group profile
     * page, and if so return the ID number.
     *
     * @param string $url
     * @return mixed int or false
     */
    public static function localGroupFromUrl($url)
    {
        $template = common_local_url('groupbyid', array('id' => '31337'));
        $template = preg_quote($template, '/');
        $template = str_replace('31337', '(\d+)', $template);
        if (preg_match("/$template/", $url, $matches)) {
            return intval($matches[1]);
        }
        return false;
    }
}

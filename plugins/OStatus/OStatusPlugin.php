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
 * OStatusPlugin implementation for GNU Social
 *
 * Depends on: WebFinger plugin
 *
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('GNUSOCIAL')) { exit(1); }

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/phpseclib');

class OStatusPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param URLMapper $m path-to-action mapper
     * @return boolean hook return
     */
    public function onRouterInitialized(URLMapper $m)
    {
        // Discovery actions
        $m->connect('main/ostatustag',
                    array('action' => 'ostatustag'));
        $m->connect('main/ostatustag?nickname=:nickname',
                    array('action' => 'ostatustag'), array('nickname' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatus/nickname/:nickname',
                  array('action' => 'ostatusinit'), array('nickname' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatus/group/:group',
                  array('action' => 'ostatusinit'), array('group' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatus/peopletag/:peopletag/tagger/:tagger',
                  array('action' => 'ostatusinit'), array('tagger' => '[A-Za-z0-9_-]+',
                                                          'peopletag' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatus',
                    array('action' => 'ostatusinit'));

        // Remote subscription actions
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'));
        $m->connect('main/ostatusgroup',
                    array('action' => 'ostatusgroup'));
        $m->connect('main/ostatuspeopletag',
                    array('action' => 'ostatuspeopletag'));

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
        $m->connect('main/salmon/peopletag/:id',
                    array('action' => 'peopletagsalmon'),
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
        if ($notice->inScope(null)) {
            // put our transport first, in case there's any conflict (like OMB)
            array_unshift($transports, 'ostatus');
            $this->log(LOG_INFO, "Notice {$notice->id} queued for OStatus processing");
        } else {
            // FIXME: we don't do privacy-controlled OStatus updates yet.
            // once that happens, finer grain of control here.
            $this->log(LOG_NOTICE, "Not queueing notice {$notice->id} for OStatus because of privacy; scope = {$notice->scope}");
        }
        return true;
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
        } else if ($feed instanceof AtomGroupNoticeFeed) {
            $salmonAction = 'groupsalmon';
            $group = $feed->getGroup();
            $id = $group->id;
        } else if ($feed instanceof AtomListNoticeFeed) {
            $salmonAction = 'peopletagsalmon';
            $peopletag = $feed->getList();
            $id = $peopletag->id;
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
            $feed->addLink($salmon, array('rel' => Salmon::REL_SALMON));

            // XXX: these are deprecated, but StatusNet only looks for NS_REPLIES
            $feed->addLink($salmon, array('rel' => Salmon::NS_REPLIES));
            $feed->addLink($salmon, array('rel' => Salmon::NS_MENTIONS));
        }

        return true;
    }

    /**
     * Add in an OStatus subscribe button
     */
    function onStartProfileRemoteSubscribe($output, $profile)
    {
        $this->onStartProfileListItemActionElements($output, $profile);
        return false;
    }

    function onStartGroupSubscribe($widget, $group)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            $widget->out->elementStart('li', 'entity_subscribe');

            $url = common_local_url('ostatusinit',
                                    array('group' => $group->nickname));
            $widget->out->element('a', array('href' => $url,
                                             'class' => 'entity_remote_subscribe'),
                                // TRANS: Link to subscribe to a remote entity.
                                _m('Subscribe'));

            $widget->out->elementEnd('li');
            return false;
        }

        return true;
    }

    function onStartSubscribePeopletagForm($output, $peopletag)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            $output->elementStart('li', 'entity_subscribe');
            $profile = $peopletag->getTagger();
            $url = common_local_url('ostatusinit',
                                    array('tagger' => $profile->nickname, 'peopletag' => $peopletag->tag));
            $output->element('a', array('href' => $url,
                                        'class' => 'entity_remote_subscribe'),
                                // TRANS: Link to subscribe to a remote entity.
                                _m('Subscribe'));

            $output->elementEnd('li');
            return false;
        }

        return true;
    }

    /*
     * If the field being looked for is URI look for the profile
     */
    function onStartProfileCompletionSearch($action, $profile, $search_engine) {
        if ($action->field == 'uri') {
            $profile->joinAdd(array('id', 'user:id'));
            $profile->whereAdd('uri LIKE "%' . $profile->escape($q) . '%"');
            $profile->query();

            if ($profile->N == 0) {
                try {
                    if (Validate::email($q)) {
                        $oprofile = Ostatus_profile::ensureWebfinger($q);
                    } else if (Validate::uri($q)) {
                        $oprofile = Ostatus_profile::ensureProfileURL($q);
                    } else {
                        // TRANS: Exception in OStatus when invalid URI was entered.
                        throw new Exception(_m('Invalid URI.'));
                    }
                    return $this->filter(array($oprofile->localProfile()));

                } catch (Exception $e) {
                // TRANS: Error message in OStatus plugin. Do not translate the domain names example.com
                // TRANS: and example.net, as these are official standard domain names for use in examples.
                    $this->msg = _m("Sorry, we could not reach that address. Please make sure that the OStatus address is like nickname@example.com or http://example.net/nickname.");
                    return array();
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Find any explicit remote mentions. Accepted forms:
     *   Webfinger: @user@example.com
     *   Profile link: @example.com/mublog/user
     * @param Profile $sender
     * @param string $text input markup text
     * @param array &$mention in/out param: set of found mentions
     * @return boolean hook return value
     */
    function onEndFindMentions(Profile $sender, $text, &$mentions)
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
                    if ($oprofile instanceof Ostatus_profile && !$oprofile->isGroup()) {
                        $profile = $oprofile->localProfile();
                        $text = !empty($profile->nickname) && mb_strlen($profile->nickname) < mb_strlen($target) ?
                                $profile->nickname : $target;
                        $matches[$pos] = array('mentioned' => array($profile),
                                               'type' => 'mention',
                                               'text' => $text,
                                               'position' => $pos,
                                               'length' => mb_strlen($target),
                                               'url' => $profile->getUrl());
                    }
                } catch (Exception $e) {
                    $this->log(LOG_ERR, "Webfinger check failed: " . $e->getMessage());
                }
            }
        }

        // Profile matches: @example.com/mublog/user
        if (preg_match_all('!(?:^|\s+)@((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)*)!',
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
                        if ($oprofile instanceof Ostatus_profile && !$oprofile->isGroup()) {
                            $profile = $oprofile->localProfile();
                            $text = !empty($profile->nickname) && mb_strlen($profile->nickname) < mb_strlen($target) ?
                                    $profile->nickname : $target;
                            $matches[$pos] = array('mentioned' => array($profile),
                                                   'type' => 'mention',
                                                   'text' => $text,
                                                   'position' => $pos,
                                                   'length' => mb_strlen($target),
                                                   'url' => $profile->getUrl());
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
        if ($oprofile instanceof Ostatus_profile && !$oprofile->isGroup()) {
            try {
                $profile = $oprofile->localProfile();
            } catch (NoProfileException $e) {
                // No locally stored profile found for remote profile
                return true;
            }
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
        if ($oprofile instanceof Ostatus_profile && $oprofile->isGroup()) {
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

    public function onEndShowStylesheets(Action $action) {
        $action->cssLink($this->path('theme/base/css/ostatus.css'));
        return true;
    }

    function onEndShowStatusNetScripts($action) {
        $action->script($this->path('js/ostatus.js'));
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
        // If we don't handle this, keep the event handler going
        if ($notice->source != 'ostatus') {
            return true;
        }

        try {
            $url = $notice->getUrl();
            // If getUrl() throws exception, $url is never set
            
            $bits = parse_url($url);
            $domain = $bits['host'];
            if (substr($domain, 0, 4) == 'www.') {
                $name = substr($domain, 4);
            } else {
                $name = $domain;
            }

            // TRANS: Title. %s is a domain name.
            $title = sprintf(_m('Sent from %s via OStatus'), $domain);

            // Abort event handler, we have a name and URL!
            return false;
        } catch (InvalidUrlException $e) {
            // This just means we don't have the notice source data
            return true;
        }
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
        $oprofile = Ostatus_profile::getKV('feeduri', $feedsub->uri);
        if ($oprofile instanceof Ostatus_profile) {
            $oprofile->processFeed($feed, 'push');
        } else {
            common_log(LOG_DEBUG, "No ostatus profile for incoming feed $feedsub->uri");
        }
    }

    /**
     * Tell the FeedSub infrastructure whether we have any active OStatus
     * usage for the feed; if not it'll be able to garbage-collect the
     * feed subscription.
     *
     * @param FeedSub $feedsub
     * @param integer $count in/out
     * @return mixed hook return code
     */
    function onFeedSubSubscriberCount($feedsub, &$count)
    {
        $oprofile = Ostatus_profile::getKV('feeduri', $feedsub->uri);
        if ($oprofile instanceof Ostatus_profile) {
            $count += $oprofile->subscriberCount();
        }
        return true;
    }

    /**
     * When about to subscribe to a remote user, start a server-to-server
     * PuSH subscription if needed. If we can't establish that, abort.
     *
     * @fixme If something else aborts later, we could end up with a stray
     *        PuSH subscription. This is relatively harmless, though.
     *
     * @param Profile $profile  subscriber
     * @param Profile $other    subscribee
     *
     * @return hook return code
     *
     * @throws Exception
     */
    function onStartSubscribe(Profile $profile, Profile $other)
    {
        if (!$profile->isLocal()) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $other->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $oprofile->subscribe();
    }

    /**
     * Having established a remote subscription, send a notification to the
     * remote OStatus profile's endpoint.
     *
     * @param Profile $profile  subscriber
     * @param Profile $other    subscribee
     *
     * @return hook return code
     *
     * @throws Exception
     */
    function onEndSubscribe(Profile $profile, Profile $other)
    {
        if (!$profile->isLocal()) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $other->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $sub = Subscription::pkeyGet(array('subscriber' => $profile->id,
                                           'subscribed' => $other->id));

        $act = $sub->asActivity();

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    /**
     * Notify remote server and garbage collect unused feeds on unsubscribe.
     * @todo FIXME: Send these operations to background queues
     *
     * @param User $user
     * @param Profile $other
     * @return hook return value
     */
    function onEndUnsubscribe(Profile $profile, Profile $other)
    {
        if (!$profile->isLocal()) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $other->id);
        if (!$oprofile instanceof Ostatus_profile) {
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
        // TRANS: Title for unfollowing a remote profile.
        $act->title   = _m('TITLE','Unfollow');
        // TRANS: Success message for unsubscribe from user attempt through OStatus.
        // TRANS: %1$s is the unsubscriber's name, %2$s is the unsubscribed user's name.
        $act->content = sprintf(_m('%1$s stopped following %2$s.'),
                               $profile->getBestName(),
                               $other->getBestName());

        $act->actor   = $profile->asActivityObject();
        $act->object  = $other->asActivityObject();

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    /**
     * When one of our local users tries to join a remote group,
     * notify the remote server. If the notification is rejected,
     * deny the join.
     *
     * @param User_group $group
     * @param Profile    $profile
     *
     * @return mixed hook return value
     * @throws Exception of various kinds, some from $oprofile->subscribe();
     */
    function onStartJoinGroup($group, $profile)
    {
        $oprofile = Ostatus_profile::getKV('group_id', $group->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $oprofile->subscribe();

        // NOTE: we don't use Group_member::asActivity() since that record
        // has not yet been created.

        $act = new Activity();
        $act->id = TagURI::mint('join:%d:%d:%s',
                                $profile->id,
                                $group->id,
                                common_date_iso8601(time()));

        $act->actor = $profile->asActivityObject();
        $act->verb = ActivityVerb::JOIN;
        $act->object = $oprofile->asActivityObject();

        $act->time = time();
        // TRANS: Title for joining a remote groep.
        $act->title = _m('TITLE','Join');
        // TRANS: Success message for subscribe to group attempt through OStatus.
        // TRANS: %1$s is the member name, %2$s is the subscribed group's name.
        $act->content = sprintf(_m('%1$s has joined group %2$s.'),
                                $profile->getBestName(),
                                $oprofile->getBestName());

        if ($oprofile->notifyActivity($act, $profile)) {
            return true;
        } else {
            $oprofile->garbageCollect();
            // TRANS: Exception thrown when joining a remote group fails.
            throw new Exception(_m('Failed joining remote group.'));
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
     * @param Profile $profile
     *
     * @return mixed hook return value
     */
    function onEndLeaveGroup($group, $profile)
    {
        $oprofile = Ostatus_profile::getKV('group_id', $group->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        // Drop the PuSH subscription if there are no other subscribers.
        $oprofile->garbageCollect();

        $member = $profile;

        $act = new Activity();
        $act->id = TagURI::mint('leave:%d:%d:%s',
                                $member->id,
                                $group->id,
                                common_date_iso8601(time()));

        $act->actor = $member->asActivityObject();
        $act->verb = ActivityVerb::LEAVE;
        $act->object = $oprofile->asActivityObject();

        $act->time = time();
        // TRANS: Title for leaving a remote group.
        $act->title = _m('TITLE','Leave');
        // TRANS: Success message for unsubscribe from group attempt through OStatus.
        // TRANS: %1$s is the member name, %2$s is the unsubscribed group's name.
        $act->content = sprintf(_m('%1$s has left group %2$s.'),
                                $member->getBestName(),
                                $oprofile->getBestName());

        $oprofile->notifyActivity($act, $member);
    }

    /**
     * When one of our local users tries to subscribe to a remote peopletag,
     * notify the remote server. If the notification is rejected,
     * deny the subscription.
     *
     * @param Profile_list $peopletag
     * @param User         $user
     *
     * @return mixed hook return value
     * @throws Exception of various kinds, some from $oprofile->subscribe();
     */

    function onStartSubscribePeopletag($peopletag, $user)
    {
        $oprofile = Ostatus_profile::getKV('peopletag_id', $peopletag->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $oprofile->subscribe();

        $sub = $user->getProfile();
        $tagger = Profile::getKV($peopletag->tagger);

        $act = new Activity();
        $act->id = TagURI::mint('subscribe_peopletag:%d:%d:%s',
                                $sub->id,
                                $peopletag->id,
                                common_date_iso8601(time()));

        $act->actor = $sub->asActivityObject();
        $act->verb = ActivityVerb::FOLLOW;
        $act->object = $oprofile->asActivityObject();

        $act->time = time();
        // TRANS: Title for following a remote list.
        $act->title = _m('TITLE','Follow list');
        // TRANS: Success message for remote list follow through OStatus.
        // TRANS: %1$s is the subscriber name, %2$s is the list, %3$s is the lister's name.
        $act->content = sprintf(_m('%1$s is now following people listed in %2$s by %3$s.'),
                                $sub->getBestName(),
                                $oprofile->getBestName(),
                                $tagger->getBestName());

        if ($oprofile->notifyActivity($act, $sub)) {
            return true;
        } else {
            $oprofile->garbageCollect();
            // TRANS: Exception thrown when subscription to remote list fails.
            throw new Exception(_m('Failed subscribing to remote list.'));
        }
    }

    /**
     * When one of our local users unsubscribes to a remote peopletag, notify the remote
     * server.
     *
     * @param Profile_list $peopletag
     * @param User         $user
     *
     * @return mixed hook return value
     */

    function onEndUnsubscribePeopletag($peopletag, $user)
    {
        $oprofile = Ostatus_profile::getKV('peopletag_id', $peopletag->id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        // Drop the PuSH subscription if there are no other subscribers.
        $oprofile->garbageCollect();

        $sub = Profile::getKV($user->id);
        $tagger = Profile::getKV($peopletag->tagger);

        $act = new Activity();
        $act->id = TagURI::mint('unsubscribe_peopletag:%d:%d:%s',
                                $sub->id,
                                $peopletag->id,
                                common_date_iso8601(time()));

        $act->actor = $member->asActivityObject();
        $act->verb = ActivityVerb::UNFOLLOW;
        $act->object = $oprofile->asActivityObject();

        $act->time = time();
        // TRANS: Title for unfollowing a remote list.
        $act->title = _m('Unfollow list');
        // TRANS: Success message for remote list unfollow through OStatus.
        // TRANS: %1$s is the subscriber name, %2$s is the list, %3$s is the lister's name.
        $act->content = sprintf(_m('%1$s stopped following the list %2$s by %3$s.'),
                                $sub->getBestName(),
                                $oprofile->getBestName(),
                                $tagger->getBestName());

        $oprofile->notifyActivity($act, $user);
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
        // Only distribute local users' favor actions, remote users
        // will have already distributed theirs.
        if (!$profile->isLocal()) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $notice->profile_id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $fav = Fave::pkeyGet(array('user_id' => $profile->id,
                                   'notice_id' => $notice->id));

        if (!$fav instanceof Fave) {
            // That's weird.
            // TODO: Make pkeyGet throw exception, since this is a critical failure.
            return true;
        }

        $act = $fav->asActivity();

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    /**
     * Notify remote user it has got a new people tag
     *   - tag verb is queued
     *   - the subscription is done immediately if not present
     *
     * @param Profile_tag $ptag the people tag that was created
     * @return hook return value
     * @throws Exception of various kinds, some from $oprofile->subscribe();
     */
    function onEndTagProfile($ptag)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $ptag->tagged);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $plist = $ptag->getMeta();
        if ($plist->private) {
            return true;
        }

        $act = new Activity();

        $tagger = $plist->getTagger();
        $tagged = Profile::getKV('id', $ptag->tagged);

        $act->verb = ActivityVerb::TAG;
        $act->id   = TagURI::mint('tag_profile:%d:%d:%s',
                                  $plist->tagger, $plist->id,
                                  common_date_iso8601(time()));
        $act->time = time();
        // TRANS: Title for listing a remote profile.
        $act->title = _m('TITLE','List');
        // TRANS: Success message for remote list addition through OStatus.
        // TRANS: %1$s is the list creator's name, %2$s is the added list member, %3$s is the list name.
        $act->content = sprintf(_m('%1$s listed %2$s in the list %3$s.'),
                                $tagger->getBestName(),
                                $tagged->getBestName(),
                                $plist->getBestName());

        $act->actor  = $tagger->asActivityObject();
        $act->objects = array($tagged->asActivityObject());
        $act->target = ActivityObject::fromPeopletag($plist);

        $oprofile->notifyDeferred($act, $tagger);

        // initiate a PuSH subscription for the person being tagged
        $oprofile->subscribe();
        return true;
    }

    /**
     * Notify remote user that a people tag has been removed
     *   - untag verb is queued
     *   - the subscription is undone immediately if not required
     *     i.e garbageCollect()'d
     *
     * @param Profile_tag $ptag the people tag that was deleted
     * @return hook return value
     */
    function onEndUntagProfile($ptag)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $ptag->tagged);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $plist = $ptag->getMeta();
        if ($plist->private) {
            return true;
        }

        $act = new Activity();

        $tagger = $plist->getTagger();
        $tagged = Profile::getKV('id', $ptag->tagged);

        $act->verb = ActivityVerb::UNTAG;
        $act->id   = TagURI::mint('untag_profile:%d:%d:%s',
                                  $plist->tagger, $plist->id,
                                  common_date_iso8601(time()));
        $act->time = time();
        // TRANS: Title for unlisting a remote profile.
        $act->title = _m('TITLE','Unlist');
        // TRANS: Success message for remote list removal through OStatus.
        // TRANS: %1$s is the list creator's name, %2$s is the removed list member, %3$s is the list name.
        $act->content = sprintf(_m('%1$s removed %2$s from the list %3$s.'),
                                $tagger->getBestName(),
                                $tagged->getBestName(),
                                $plist->getBestName());

        $act->actor  = $tagger->asActivityObject();
        $act->objects = array($tagged->asActivityObject());
        $act->target = ActivityObject::fromPeopletag($plist);

        $oprofile->notifyDeferred($act, $tagger);

        // unsubscribe to PuSH feed if no more required
        $oprofile->garbageCollect();

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
        // Only distribute local users' disfavor actions, remote users
        // will have already distributed theirs.
        if (!$profile->isLocal()) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $notice->profile_id);
        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::UNFAVORITE;
        $act->id   = TagURI::mint('disfavor:%d:%d:%s',
                                  $profile->id,
                                  $notice->id,
                                  common_date_iso8601(time()));
        $act->time    = time();
        // TRANS: Title for unliking a remote notice.
        $act->title   = _m('Unlike');
        // TRANS: Success message for remove a favorite notice through OStatus.
        // TRANS: %1$s is the unfavoring user's name, %2$s is URI to the no longer favored notice.
        $act->content = sprintf(_m('%1$s no longer likes %2$s.'),
                               $profile->getBestName(),
                               $notice->getUrl());

        $act->actor   = $profile->asActivityObject();
        $act->object  = $notice->asActivityObject();

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    function onStartGetProfileUri($profile, &$uri)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $profile->id);
        if ($oprofile instanceof Ostatus_profile) {
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
        $oprofile = Ostatus_profile::getKV('group_id', $group->id);
        if ($oprofile instanceof Ostatus_profile) {
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
        if (!$action->getScoped() instanceof Profile) {
            // early return if we're not logged in
            return true;
        }

        if ($action->getScoped()->sameAs($action->getTarget())) {
            $action->elementStart('div', 'entity_actions');
            $action->elementStart('p', array('id' => 'entity_remote_subscribe',
                                             'class' => 'entity_subscribe'));
            $action->element('a', array('href' => common_local_url($target),
                                        'class' => 'entity_remote_subscribe'),
                                // TRANS: Link text for link to remote subscribe.
                                _m('Remote'));
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
        $user = User::getKV('id', $profile->id);

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
        // TRANS: Title for activity.
        $act->title   = _m('Profile update');
        // TRANS: Ping text for remote profile update through OStatus.
        // TRANS: %s is user that updated their profile.
        $act->content = sprintf(_m('%s has updated their profile page.'),
                               $profile->getBestName());

        $act->actor   = $profile->asActivityObject();
        $act->object  = $act->actor;

        while ($oprofile->fetch()) {
            $oprofile->notifyDeferred($act, $profile);
        }

        return true;
    }

    // FIXME: This one can accept both an Action and a Widget. Confusing! Refactor to (HTMLOutputter $out, Profile $target)!
    function onStartProfileListItemActionElements($item)
    {
        if (common_logged_in()) {
            // only non-logged in users get to see the "remote subscribe" form
            return true;
        } elseif (!$item->getTarget()->isLocal()) {
            // we can (for now) only provide remote subscribe forms for local users
            return true;
        }

        if ($item instanceof ProfileAction) {
            $output = $item;
        } elseif ($item instanceof Widget) {
            $output = $item->out;
        } else {
            // Bad $item class, don't know how to use this for outputting!
            throw new ServerException('Bad item type for onStartProfileListItemActionElements');
        }

        // Add an OStatus subscribe
        $output->elementStart('li', 'entity_subscribe');
        $url = common_local_url('ostatusinit',
                                array('nickname' => $item->getTarget()->getNickname()));
        $output->element('a', array('href' => $url,
                                    'class' => 'entity_remote_subscribe'),
                          // TRANS: Link text for a user to subscribe to an OStatus user.
                         _m('Subscribe'));
        $output->elementEnd('li');

        $output->elementStart('li', 'entity_tag');
        $url = common_local_url('ostatustag',
                                array('nickname' => $item->getTarget()->getNickname()));
        $output->element('a', array('href' => $url,
                                    'class' => 'entity_remote_tag'),
                          // TRANS: Link text for a user to list an OStatus user.
                         _m('List'));
        $output->elementEnd('li');

        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'OStatus',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou, James Walker, Brion Vibber, Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:OStatus',
                            // TRANS: Plugin description.
                            'rawdescription' => _m('Follow people across social networks that implement '.
                               '<a href="http://ostatus.org/">OStatus</a>.'));

        return true;
    }

    /**
     * Utility function to check if the given URI is a canonical group profile
     * page, and if so return the ID number.
     *
     * @param string $url
     * @return mixed int or false
     */
    public static function localGroupFromUrl($url)
    {
        $group = User_group::getKV('uri', $url);
        if ($group instanceof User_group) {
            if ($group->isLocal()) {
                return $group->id;
            }
        } else {
            // To find local groups which haven't had their uri fields filled out...
            // If the domain has changed since a subscriber got the URI, it'll
            // be broken.
            $template = common_local_url('groupbyid', array('id' => '31337'));
            $template = preg_quote($template, '/');
            $template = str_replace('31337', '(\d+)', $template);
            if (preg_match("/$template/", $url, $matches)) {
                return intval($matches[1]);
            }
        }
        return false;
    }

    public function onStartProfileGetAtomFeed($profile, &$feed)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $profile->id);

        if (!$oprofile instanceof Ostatus_profile) {
            return true;
        }

        $feed = $oprofile->feeduri;
        return false;
    }

    function onStartGetProfileFromURI($uri, &$profile)
    {
        // Don't want to do Web-based discovery on our own server,
        // so we check locally first.

        $user = User::getKV('uri', $uri);

        if (!empty($user)) {
            $profile = $user->getProfile();
            return false;
        }

        // Now, check remotely

        try {
            $oprofile = Ostatus_profile::ensureProfileURI($uri);
            $profile = $oprofile->localProfile();
            return !($profile instanceof Profile);  // localProfile won't throw exception but can return null
        } catch (Exception $e) {
            return true; // It's not an OStatus profile as far as we know, continue event handling
        }
    }

    function onEndWebFingerNoticeLinks(XML_XRD $xrd, Notice $target)
    {
        $author = $target->getProfile();
        $profiletype = $this->profileTypeString($author);
        $salmon_url = common_local_url("{$profiletype}salmon", array('id' => $author->id));
        $xrd->links[] = new XML_XRD_Element_Link(Salmon::REL_SALMON, $salmon_url);
        return true;
    }

    function onEndWebFingerProfileLinks(XML_XRD $xrd, Profile $target)
    {
        if ($target->getObjectType() === ActivityObject::PERSON) {
            $this->addWebFingerPersonLinks($xrd, $target);
        }

        // Salmon
        $profiletype = $this->profileTypeString($target);
        $salmon_url = common_local_url("{$profiletype}salmon", array('id' => $target->id));

        $xrd->links[] = new XML_XRD_Element_Link(Salmon::REL_SALMON, $salmon_url);

        // XXX: these are deprecated, but StatusNet only looks for NS_REPLIES
        $xrd->links[] = new XML_XRD_Element_Link(Salmon::NS_REPLIES, $salmon_url);
        $xrd->links[] = new XML_XRD_Element_Link(Salmon::NS_MENTIONS, $salmon_url);

        // TODO - finalize where the redirect should go on the publisher
        $xrd->links[] = new XML_XRD_Element_Link('http://ostatus.org/schema/1.0/subscribe',
                              common_local_url('ostatussub') . '?profile={uri}',
                              null, // type not set
                              true); // isTemplate

        return true;
    }

    protected function profileTypeString(Profile $target)
    {
        // This is just used to have a definitive string response to "USERsalmon" or "GROUPsalmon"
        switch ($target->getObjectType()) {
        case ActivityObject::PERSON:
            return 'user';
        case ActivityObject::GROUP:
            return 'group';
        default:
            throw new ServerException('Unknown profile type for WebFinger profile links');
        }
    }

    protected function addWebFingerPersonLinks(XML_XRD $xrd, Profile $target)
    {
        $xrd->links[] = new XML_XRD_Element_Link(Discovery::UPDATESFROM,
                            common_local_url('ApiTimelineUser',
                                array('id' => $target->id, 'format' => 'atom')),
                            'application/atom+xml');

        // Get this profile's keypair
        $magicsig = Magicsig::getKV('user_id', $target->id);
        if (!$magicsig instanceof Magicsig && $target->isLocal()) {
            $magicsig = Magicsig::generate($target->getUser());
        }

        if (!$magicsig instanceof Magicsig) {
            return false;   // value doesn't mean anything, just figured I'd indicate this function didn't do anything
        }
        if (Event::handle('StartAttachPubkeyToUserXRD', array($magicsig, $xrd, $target))) {
            $xrd->links[] = new XML_XRD_Element_Link(Magicsig::PUBLICKEYREL,
                                'data:application/magic-public-key,'. $magicsig->toString());
            // The following event handles plugins like Diaspora which add their own version of the Magicsig pubkey
            Event::handle('EndAttachPubkeyToUserXRD', array($magicsig, $xrd, $target));
        }
    }

    public function onGetLocalAttentions(Profile $actor, array $attention_uris, array &$mentions, array &$groups)
    {
        list($mentions, $groups) = Ostatus_profile::filterAttention($actor, $attention_uris);
    }

    // FIXME: Maybe this shouldn't be so authoritative that it breaks other remote profile lookups?
    static public function onCheckActivityAuthorship(Activity $activity, Profile &$profile)
    {
        try {
            $oprofile = Ostatus_profile::ensureProfileURL($profile->getUrl());
            $profile = $oprofile->checkAuthorship($activity);
        } catch (Exception $e) {
            common_log(LOG_ERR, 'Could not get a profile or check authorship ('.get_class($e).': "'.$e->getMessage().'") for activity ID: '.$activity->id);
            $profile = null;
            return false;
        }
        return true;
    }

    public function onProfileDeleteRelated($profile, &$related)
    {
        // Ostatus_profile has a 'profile_id' property, which will be used to find the object
        $related[] = 'Ostatus_profile';

        // Magicsig has a "user_id" column instead, so we have to delete it more manually:
        $magicsig = Magicsig::getKV('user_id', $profile->id);
        if ($magicsig instanceof Magicsig) {
            $magicsig->delete();
        }
        return true;
    }
}

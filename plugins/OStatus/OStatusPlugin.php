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

if (!defined('STATUSNET')) {
    exit(1);
}

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/');

class FeedSubException extends Exception
{
    function __construct($msg=null)
    {
        $type = get_class($this);
        if ($msg) {
            parent::__construct("$type: $msg");
        } else {
            parent::__construct($type);
        }
    }
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
        $m->connect('main/ownerxrd',
                    array('action' => 'ownerxrd'));
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

            // XXX: these are deprecated
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

    function onStartShowTagProfileForm($action, $profile)
    {
        $action->elementStart('form', array('method' => 'post',
                                           'id' => 'form_tag_user',
                                           'class' => 'form_settings',
                                           'name' => 'tagprofile',
                                           'action' => common_local_url('tagprofile', array('id' => @$profile->id))));

        $action->elementStart('fieldset');
        // TRANS: Fieldset legend.
        $action->element('legend', null, _m('List remote profile'));
        $action->hidden('token', common_session_token());

        $user = common_current_user();

        $action->elementStart('ul', 'form_data');
        $action->elementStart('li');

        // TRANS: Field label.
        $action->input('uri', _m('LABEL','Remote profile'), $action->trimmed('uri'),
                     // TRANS: Field title.
                     _m('OStatus user\'s address, like nickname@example.com or http://example.net/nickname.'));
        $action->elementEnd('li');
        $action->elementEnd('ul');
        // TRANS: Button text to fetch remote profile.
        $action->submit('fetch', _m('BUTTON','Fetch'));
        $action->elementEnd('fieldset');
        $action->elementEnd('form');
    }

    function onStartTagProfileAction($action, $profile)
    {
        $err = null;
        $uri = $action->trimmed('uri');

        if (!$profile && $uri) {
            try {
                if (Validate::email($uri)) {
                    $oprofile = Ostatus_profile::ensureWebfinger($uri);
                } else if (Validate::uri($uri)) {
                    $oprofile = Ostatus_profile::ensureProfileURL($uri);
                } else {
                    // TRANS: Exception in OStatus when invalid URI was entered.
                    throw new Exception(_m('Invalid URI.'));
                }

                // redirect to the new profile.
                common_redirect(common_local_url('tagprofile', array('id' => $oprofile->profile_id)), 303);
                return false;

            } catch (Exception $e) {
                // TRANS: Error message in OStatus plugin. Do not translate the domain names example.com
                // TRANS: and example.net, as these are official standard domain names for use in examples.
                $err = _m("Sorry, we could not reach that address. Please make sure that the OStatus address is like nickname@example.com or http://example.net/nickname.");
            }

            $action->showForm($err);
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
                // TRANS: Title. %s is a domain name.
                $title = sprintf(_m('Sent from %s via OStatus'), $domain);
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
        $oprofile = Ostatus_profile::getKV('feeduri', $feedsub->uri);
        if ($oprofile) {
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
        if ($oprofile) {
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

        if (empty($oprofile)) {
            return true;
        }

        if (!$oprofile->subscribe()) {
            // TRANS: Exception thrown when setup of remote subscription fails.
            throw new Exception(_m('Could not set up remote subscription.'));
        }
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

        if (empty($oprofile)) {
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
        // TRANS: Title for unfollowing a remote profile.
        $act->title   = _m('TITLE','Unfollow');
        // TRANS: Success message for unsubscribe from user attempt through OStatus.
        // TRANS: %1$s is the unsubscriber's name, %2$s is the unsubscribed user's name.
        $act->content = sprintf(_m('%1$s stopped following %2$s.'),
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
     * @param Profile    $profile
     *
     * @return mixed hook return value
     */
    function onStartJoinGroup($group, $profile)
    {
        $oprofile = Ostatus_profile::getKV('group_id', $group->id);
        if ($oprofile) {
            if (!$oprofile->subscribe()) {
                // TRANS: Exception thrown when setup of remote group membership fails.
                throw new Exception(_m('Could not set up remote group membership.'));
            }

            // NOTE: we don't use Group_member::asActivity() since that record
            // has not yet been created.

            $act = new Activity();
            $act->id = TagURI::mint('join:%d:%d:%s',
                                    $profile->id,
                                    $group->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($profile);
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
        if ($oprofile) {
            // Drop the PuSH subscription if there are no other subscribers.
            $oprofile->garbageCollect();

            $member = $profile;

            $act = new Activity();
            $act->id = TagURI::mint('leave:%d:%d:%s',
                                    $member->id,
                                    $group->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($member);
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
     */

    function onStartSubscribePeopletag($peopletag, $user)
    {
        $oprofile = Ostatus_profile::getKV('peopletag_id', $peopletag->id);
        if ($oprofile) {
            if (!$oprofile->subscribe()) {
                // TRANS: Exception thrown when setup of remote list subscription fails.
                throw new Exception(_m('Could not set up remote list subscription.'));
            }

            $sub = $user->getProfile();
            $tagger = Profile::getKV($peopletag->tagger);

            $act = new Activity();
            $act->id = TagURI::mint('subscribe_peopletag:%d:%d:%s',
                                    $sub->id,
                                    $peopletag->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($sub);
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
        if ($oprofile) {
            // Drop the PuSH subscription if there are no other subscribers.
            $oprofile->garbageCollect();

            $sub = Profile::getKV($user->id);
            $tagger = Profile::getKV($peopletag->tagger);

            $act = new Activity();
            $act->id = TagURI::mint('unsubscribe_peopletag:%d:%d:%s',
                                    $sub->id,
                                    $peopletag->id,
                                    common_date_iso8601(time()));

            $act->actor = ActivityObject::fromProfile($member);
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
        $user = User::getKV('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $notice->profile_id);

        if (empty($oprofile)) {
            return true;
        }

        $fav = Fave::pkeyGet(array('user_id' => $user->id,
                                   'notice_id' => $notice->id));

        if (empty($fav)) {
            // That's weird.
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
     */
    function onEndTagProfile($ptag)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $ptag->tagged);

        if (empty($oprofile)) {
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

        $act->actor  = ActivityObject::fromProfile($tagger);
        $act->objects = array(ActivityObject::fromProfile($tagged));
        $act->target = ActivityObject::fromPeopletag($plist);

        $oprofile->notifyDeferred($act, $tagger);

        // initiate a PuSH subscription for the person being tagged
        if (!$oprofile->subscribe()) {
            // TRANS: Exception thrown when subscribing to a remote list fails.
            throw new Exception(sprintf(_m('Could not complete subscription to remote '.
                                          'profile\'s feed. List %s could not be saved.'), $ptag->tag));
            return false;
        }
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

        if (empty($oprofile)) {
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

        $act->actor  = ActivityObject::fromProfile($tagger);
        $act->objects = array(ActivityObject::fromProfile($tagged));
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
        $user = User::getKV('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::getKV('profile_id', $notice->profile_id);

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
        // TRANS: Title for unliking a remote notice.
        $act->title   = _m('Unlike');
        // TRANS: Success message for remove a favorite notice through OStatus.
        // TRANS: %1$s is the unfavoring user's name, %2$s is URI to the no longer favored notice.
        $act->content = sprintf(_m('%1$s no longer likes %2$s.'),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromNotice($notice);

        $oprofile->notifyActivity($act, $profile);

        return true;
    }

    function onStartGetProfileUri($profile, &$uri)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $profile->id);
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
        $oprofile = Ostatus_profile::getKV('group_id', $group->id);
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

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = $act->actor;

        while ($oprofile->fetch()) {
            $oprofile->notifyDeferred($act, $profile);
        }

        return true;
    }

    function onStartProfileListItemActionElements($item, $profile=null)
    {
        if (!common_logged_in()) {

            $profileUser = User::getKV('id', $item->profile->id);

            if (!empty($profileUser)) {

                if ($item instanceof Action) {
                    $output = $item;
                    $profile = $item->profile;
                } else {
                    $output = $item->out;
                }

                // Add an OStatus subscribe
                $output->elementStart('li', 'entity_subscribe');
                $url = common_local_url('ostatusinit',
                                        array('nickname' => $profileUser->nickname));
                $output->element('a', array('href' => $url,
                                            'class' => 'entity_remote_subscribe'),
                                  // TRANS: Link text for a user to subscribe to an OStatus user.
                                 _m('Subscribe'));
                $output->elementEnd('li');

                $output->elementStart('li', 'entity_tag');
                $url = common_local_url('ostatustag',
                                        array('nickname' => $profileUser->nickname));
                $output->element('a', array('href' => $url,
                                            'class' => 'entity_remote_tag'),
                                  // TRANS: Link text for a user to list an OStatus user.
                                 _m('List'));
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
        if ($group) {
            $local = Local_group::getKV('group_id', $group->id);
            if ($local) {
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

        if (empty($oprofile)) {
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

        $oprofile = Ostatus_profile::ensureProfileURI($uri);

        if (!empty($oprofile)) {
            $profile = $oprofile->localProfile();
            return false;
        }

        // Still not a hit, so give up.

        return true;
    }

    function onEndXrdActionLinks(&$xrd, $user)
    {
        $xrd->links[] = array('rel' => Discovery::UPDATESFROM,
                  'href' => common_local_url('ApiTimelineUser',
                             array('id' => $user->id,
                                   'format' => 'atom')),
                  'type' => 'application/atom+xml');

                // Salmon
        $salmon_url = common_local_url('usersalmon',
                                       array('id' => $user->id));

        $xrd->links[] = array('rel' => Salmon::REL_SALMON,
                              'href' => $salmon_url);
        // XXX : Deprecated - to be removed.
        $xrd->links[] = array('rel' => Salmon::NS_REPLIES,
                              'href' => $salmon_url);

        $xrd->links[] = array('rel' => Salmon::NS_MENTIONS,
                              'href' => $salmon_url);

        // Get this user's keypair
        $magickey = Magicsig::getKV('user_id', $user->id);
        if (!$magickey) {
            // No keypair yet, let's generate one.
            $magickey = new Magicsig();
            $magickey->generate($user->id);
        }

        $xrd->links[] = array('rel' => Magicsig::PUBLICKEYREL,
                              'href' => 'data:application/magic-public-key,'. $magickey->toString(false));

        // TODO - finalize where the redirect should go on the publisher
        $url = common_local_url('ostatussub') . '?profile={uri}';
        $xrd->links[] = array('rel' => 'http://ostatus.org/schema/1.0/subscribe',
                              'template' => $url );

        return true;
    }
}

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
class SharePlugin extends ActivityVerbHandlerPlugin
{
    public function tag()
    {
        return 'share';
    }

    public function types()
    {
        return array();
    }

    public function verbs()
    {
        return array(ActivityVerb::SHARE);
    }

    // Share is a bit special and $act->objects[0] should be an Activity
    // instead of ActivityObject! Therefore also $act->objects[0]->type is not set.
    public function isMyActivity(Activity $act) {
        return (count($act->objects) == 1
            && ($act->objects[0] instanceof Activity)
            && $this->isMyVerb($act->verb));
    }

    public function onRouterInitialized(URLMapper $m)
    {
        // Web UI actions
        $m->connect('main/repeat', array('action' => 'repeat'));

        // Share for Twitter API ("Retweet")
        $m->connect('api/statuses/retweeted_by_me.:format',
                    array('action' => 'ApiTimelineRetweetedByMe',
                          'format' => '(xml|json|atom|as)'));

        $m->connect('api/statuses/retweeted_to_me.:format',
                    array('action' => 'ApiTimelineRetweetedToMe',
                          'format' => '(xml|json|atom|as)'));

        $m->connect('api/statuses/retweets_of_me.:format',
                    array('action' => 'ApiTimelineRetweetsOfMe',
                          'format' => '(xml|json|atom|as)'));

        $m->connect('api/statuses/retweet/:id.:format',
                    array('action' => 'ApiStatusesRetweet',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));

        $m->connect('api/statuses/retweets/:id.:format',
                    array('action' => 'ApiStatusesRetweets',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));
    }

    // FIXME: Set this to abstract public in lib/activityhandlerplugin.php when all plugins have migrated!
    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())
    {
        assert($this->isMyActivity($act));

        // The below algorithm is mainly copied from the previous Ostatus_profile->processShare()

        if (count($act->objects) !== 1) {
            // TRANS: Client exception thrown when trying to share multiple activities at once.
            throw new ClientException(_m('Can only handle share activities with exactly one object.'));
        }

        $shared = $act->objects[0];
        if (!$shared instanceof Activity) {
            // TRANS: Client exception thrown when trying to share a non-activity object.
            throw new ClientException(_m('Can only handle shared activities.'));
        }

        $sharedUri = $shared->id;
        if (!empty($shared->objects[0]->id)) {
            // Because StatusNet since commit 8cc4660 sets $shared->id to a TagURI which
            // fucks up federation, because the URI is no longer recognised by the origin.
            // So we set it to the object ID if it exists, otherwise we trust $shared->id
            $sharedUri = $shared->objects[0]->id;
        }
        if (empty($sharedUri)) {
            throw new ClientException(_m('Shared activity does not have an id'));
        }

        try {
            // First check if we have the shared activity. This has to be done first, because
            // we can't use these functions to "ensureActivityObjectProfile" of a local user,
            // who might be the creator of the shared activity in question.
            $sharedNotice = Notice::getByUri($sharedUri);
        } catch (NoResultException $e) {
            // If no locally stored notice is found, process it!
            // TODO: Remember to check Deleted_notice!
            // TODO: If a post is shared that we can't retrieve - what to do?
            $other = Ostatus_profile::ensureActivityObjectProfile($shared->actor);
            $sharedNotice = Notice::saveActivity($shared, $other->localProfile(), array('source'=>'share'));
        } catch (FeedSubException $e) {
            // Remote feed could not be found or verified, should we
            // transform this into an "RT @user Blah, blah, blah..."?
            common_log(LOG_INFO, __METHOD__ . ' got a ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }

        // Setting this here because when the algorithm gets back to
        // Notice::saveActivity it will update the Notice object.
        $stored->repeat_of = $sharedNotice->getID();
        $stored->conversation = $sharedNotice->conversation;
        $stored->object_type = ActivityUtils::resolveUri(ActivityObject::ACTIVITY, true);

        // We don't have to save a repeat in a separate table, we can
        // find repeats by just looking at the notice.repeat_of field.

        // By returning true here instead of something that evaluates
        // to false, we show that we have processed everything properly.
        return true;
    }

    // FIXME: Put this in lib/activityhandlerplugin.php when we're ready
    //          with the other microapps/activityhandlers as well.
    //          Also it should be StartNoticeAsActivity (with a prepped Activity, including ->context etc.)
    public function onEndNoticeAsActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        if (!$this->isMyNotice($stored)) {
            return true;
        }

        common_debug('Extending activity '.$stored->id.' with '.get_called_class());
        $this->extendActivity($stored, $act, $scoped);
        return false;
    }

    public function extendActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        // TODO: How to handle repeats of deleted notices?
        $target = Notice::getByID($stored->repeat_of);
        // TRANS: A repeat activity's title. %1$s is repeater's nickname
        //        and %2$s is the repeated user's nickname.
        $act->title = sprintf(_('%1$s repeated a notice by %2$s'),
                              $stored->getProfile()->getNickname(),
                              $target->getProfile()->getNickname());
        $act->objects[] = $target->asActivity($scoped);
    }

    public function activityObjectFromNotice(Notice $stored)
    {
        // Repeat is a little bit special. As it's an activity, our
        // ActivityObject is instead turned into an Activity
        $object          = new Activity();
        $object->actor   = $stored->getProfile()->asActivityObject();
        $object->verb    = ActivityVerb::SHARE;
        $object->content = $stored->rendered;
        $this->extendActivity($stored, $object);

        return $object;
    }

    public function deleteRelated(Notice $notice)
    {
        // No action needed as we don't have a separate table for share objects.
        return true;
    }

    // Layout stuff

    /**
     * show a link to the author of repeat
     *
     * FIXME: Some repeat stuff still in lib/noticelistitem.php! ($nli->repeat etc.)
     */
    public function onEndShowNoticeInfo(NoticeListItem $nli)
    {
        if (!empty($nli->repeat)) {
            $repeater = $nli->repeat->getProfile();

            $attrs = array('href' => $repeater->getUrl(),
                           'class' => 'h-card p-author',
                           'title' => $repeater->getFancyName());

            $nli->out->elementStart('span', 'repeat');

            // TRANS: Addition in notice list item if notice was repeated. Followed by a span with a nickname.
            $nli->out->raw(_('Repeated by').' ');

            $nli->out->element('a', $attrs, $repeater->getNickname());

            $nli->out->elementEnd('span');
        }
    }

    public function onEndShowThreadedNoticeTailItems(NoticeListItem $nli, Notice $notice, &$threadActive)
    {
        if ($nli instanceof ThreadedNoticeListSubItem) {
            // The sub-items are replies to a conversation, thus we use different HTML elements etc.
            $item = new ThreadedNoticeListInlineRepeatsItem($notice, $nli->out);
        } else {
            $item = new ThreadedNoticeListRepeatsItem($notice, $nli->out);
        }
        $threadActive = $item->show() || $threadActive;
        return true;
    }

    /**
     * show the "repeat" form in the notice options element
     * FIXME: Don't let a NoticeListItemAdapter slip in here (or extend that from NoticeListItem)
     *
     * @return void
     */
    public function onEndShowNoticeOptionItems($nli)
    {
        // FIXME: Use bitmasks (but be aware that PUBLIC_SCOPE is 0!)
        // Also: AHHH, $scope and $scoped are scarily similar looking.
        $scope = $nli->notice->getScope();
        if ($scope === Notice::PUBLIC_SCOPE || $scope === Notice::SITE_SCOPE) {
            $scoped = Profile::current();
            if ($scoped instanceof Profile &&
                    $scoped->getID() !== $nli->notice->getProfile()->getID()) {

                if ($scoped->hasRepeated($nli->notice)) {
                    $nli->out->element('span', array('class' => 'repeated',
                                                      // TRANS: Title for repeat form status in notice list when a notice has been repeated.
                                                      'title' => _('Notice repeated.')),
                                        // TRANS: Repeat form status in notice list when a notice has been repeated.
                                        _('Repeated'));
                } else {
                    $repeat = new RepeatForm($nli->out, $nli->notice);
                    $repeat->show();
                }
            }
        }
    }

    protected function showNoticeListItem(NoticeListItem $nli)
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

    // API stuff

    /**
     * Typically just used to fill out Twitter-compatible API status data.
     *
     * FIXME: Make all the calls before this end up with a Notice instead of ArrayWrapper please...
     */
    public function onNoticeSimpleStatusArray($notice, array &$status, Profile $scoped=null, array $args=array())
    {
        $status['repeated'] = $scoped instanceof Profile
                            ? $scoped->hasRepeated($notice)
                            : false;

        if ($status['repeated'] === true) {
            // Qvitter API wants the "repeated_id" value set too.
            $repeated = Notice::pkeyGet(array('profile_id' => $scoped->getID(),
                                              'repeat_of' => $notice->getID(),
                                              'verb' => ActivityVerb::SHARE));
            $status['repeated_id'] = $repeated->getID();
        }
    }

    public function onTwitterUserArray(Profile $profile, array &$userdata, Profile $scoped=null, array $args=array())
    {
        $userdata['favourites_count'] = Fave::countByProfile($profile);
    }

    // Command stuff

    /**
     * EndInterpretCommand for RepeatPlugin will handle the 'repeat' command
     * using the class RepeatCommand.
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
        if ($result === false && in_array($cmd, array('repeat', 'rp', 'rt', 'rd'))) {
            if (empty($arg)) {
                $result = null;
            } else {
                list($other, $extra) = CommandInterpreter::split_arg($arg);
                if (!empty($extra)) {
                    $result = null;
                } else {
                    $result = new RepeatCommand($user, $other);
                }
            }
            return false;
        }
        return true;
    }

    public function onHelpCommandMessages(HelpCommand $help, array &$commands)
    {
        // TRANS: Help message for IM/SMS command "repeat #<notice_id>".
        $commands['repeat #<notice_id>'] = _m('COMMANDHELP', "repeat a notice with a given id");
        // TRANS: Help message for IM/SMS command "repeat <nickname>".
        $commands['repeat <nickname>']   = _m('COMMANDHELP', "repeat the last notice from user");
    }

    /**
     * Are we allowed to perform a certain command over the API?
     */
    public function onCommandSupportedAPI(Command $cmd, &$supported)
    {
        $supported = $supported || $cmd instanceof RepeatCommand;
    }

    protected function getActionTitle(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        // return page title
    }

    protected function doActionPreparation(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        // prepare Action?
    }

    protected function doActionPost(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        // handle repeat POST
    }

    protected function getActivityForm(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        return new RepeatForm($action, $target);
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Share verb',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Shares (repeats) using ActivityStreams.'));

        return true;
    }
}
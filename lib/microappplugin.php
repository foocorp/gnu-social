<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Superclass for microapp plugin
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
 * @category  Microapp
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Superclass for microapp plugins
 *
 * This class lets you define micro-applications with different kinds of activities.
 *
 * The applications work more-or-less like other
 *
 * @category  Microapp
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class MicroAppPlugin extends Plugin
{
    /**
     * Returns a localized string which represents this micro-app,
     * to be shown to users selecting what type of post to make.
     * This is paired with the key string in $this->tag().
     *
     * All micro-app classes must override this method.
     *
     * @return string
     */
    abstract function appTitle();

    /**
     * Returns a key string which represents this micro-app in HTML
     * ids etc, as when offering selection of what type of post to make.
     * This is paired with the user-visible localizable $this->appTitle().
     *
     * All micro-app classes must override this method.
     */
    abstract function tag();

    /**
     * Return a list of ActivityStreams object type URIs
     * which this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams object belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * All micro-app classes must override this method.
     *
     * @fixme can we confirm that these types are the same
     * for Atom and JSON streams? Any limitations or issues?
     *
     * @return array of strings
     */
    abstract function types();

    /**
     * Given a parsed ActivityStreams activity, your plugin
     * gets to figure out how to actually save it into a notice
     * and any additional data structures you require.
     *
     * This will handle things received via AtomPub, OStatus
     * (PuSH and Salmon transports), or ActivityStreams-based
     * backup/restore of account data.
     *
     * You should be able to accept as input the output from your
     * $this->activityObjectFromNotice(). Where applicable, try to
     * use existing ActivityStreams structures and object types,
     * and be liberal in accepting input from what might be other
     * compatible apps.
     *
     * All micro-app classes must override this method.
     *
     * @fixme are there any standard options?
     *
     * @param Activity $activity
     * @param Profile $actor
     * @param array $options=array()
     *
     * @return Notice the resulting notice
     */
    abstract function saveNoticeFromActivity($activity, $actor, $options=array());

    /**
     * Given an existing Notice object, your plugin gets to
     * figure out how to arrange it into an ActivityStreams
     * object.
     *
     * This will be how your specialized notice gets output in
     * Atom feeds and JSON-based ActivityStreams output, including
     * account backup/restore and OStatus (PuSH and Salmon transports).
     *
     * You should be able to round-trip data from this format back
     * through $this->saveNoticeFromActivity(). Where applicable, try
     * to use existing ActivityStreams structures and object types,
     * and consider interop with other compatible apps.
     *
     * All micro-app classes must override this method.
     *
     * @fixme this outputs an ActivityObject, not an Activity. Any compat issues?
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */
    abstract function activityObjectFromNotice($notice);

    /**
     * When building the primary notice form, we'll fetch also some
     * alternate forms for specialized types -- that's you!
     *
     * Return a custom Widget or Form object for the given output
     * object, and it'll be included in the HTML output. Beware that
     * your form may be initially hidden.
     *
     * All micro-app classes must override this method.
     *
     * @param HTMLOutputter $out
     * @return Widget
     */
    abstract function entryForm($out);

    /**
     * When a notice is deleted, you'll be called here for a chance
     * to clean up any related resources.
     *
     * All micro-app classes must override this method.
     *
     * @param Notice $notice
     */
    abstract function deleteRelated($notice);

    /**
     *
     */
    public function newFormAction() {
        // such as 'newbookmark' or 'newevent' route
        return 'new'.$this->tag();
    }

    /**
     * Check if a given notice object should be handled by this micro-app
     * plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(). You can override this method to expand
     * your checks.
     *
     * @param Notice $notice
     * @return boolean
     */
    function isMyNotice($notice) {
        $types = $this->types();
        return ($notice->verb == ActivityVerb::POST) && in_array($notice->object_type, $types);
    }

    /**
     * Check if a given ActivityStreams activity should be handled by this
     * micro-app plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(), and requires that exactly one matching
     * object be present. You can override this method to expand
     * your checks or to compare the activity's verb, etc.
     *
     * @param Activity $activity
     * @return boolean
     */
    function isMyActivity($activity) {
        $types = $this->types();
        return (count($activity->objects) == 1 &&
                ($activity->objects[0] instanceof ActivityObject) &&
                ($activity->verb == ActivityVerb::POST) &&
                in_array($activity->objects[0]->type, $types));
    }

    /**
     * Called when generating Atom XML ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Note that you can only add output of additional XML elements,
     * not change existing stuff here.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param XMLOutputter $out to add elements at end of object
     */
    function activityObjectOutputAtom(ActivityObject $obj, XMLOutputter $out)
    {
        // default is a no-op
    }

    /**
     * Called when generating JSON ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Modify the array contents to your heart's content, and it'll
     * all get serialized out as JSON.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param array &$out JSON-targeted array which can be modified
     */
    public function activityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        // default is a no-op
    }

    /**
     * When a notice is deleted, delete the related objects
     * by calling the overridable $this->deleteRelated().
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */
    function onNoticeDeleteRelated($notice)
    {
        if (!$this->isMyNotice($notice)) {
            return true;
        }

        $this->deleteRelated($notice);
    }

    /**
     * Output the HTML for this kind of object in a list
     *
     * @param NoticeListItem $nli The list item being shown.
     *
     * @return boolean hook value
     *
     * @fixme WARNING WARNING WARNING this closes a 'div' that is implicitly opened in BookmarkPlugin's showNotice implementation
     */
    function onStartShowNoticeItem($nli)
    {
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $adapter = $this->adaptNoticeListItem($nli);

        if (!empty($adapter)) {
            $adapter->showNotice();
            $adapter->showNoticeAttachments();
            $adapter->showNoticeInfo();
            $adapter->showNoticeOptions();
        } else {
            $this->oldShowNotice($nli);
        }

        return false;
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
      return null;
    }

    function oldShowNotice($nli)
    {
        $out = $nli->out;
        $notice = $nli->notice;

        try {
            $this->showNotice($notice, $out);
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            // try to fall back
            $out->elementStart('div');
            $nli->showAuthor();
            $nli->showContent();
        }

        $nli->showNoticeLink();
        $nli->showNoticeSource();
        $nli->showNoticeLocation();
        $nli->showContext();
        $nli->showRepeat();

        $out->elementEnd('div');

        $nli->showNoticeOptions();
    }

    /**
     * Render a notice as one of our objects
     *
     * @param Notice         $notice  Notice to render
     * @param ActivityObject &$object Empty object to fill
     *
     * @return boolean hook value
     */
    function onStartActivityObjectFromNotice($notice, &$object)
    {
        if (!$this->isMyNotice($notice)) {
            return true;
        }

        $object = $this->activityObjectFromNotice($notice);
        return false;
    }

    /**
     * Handle a posted object from PuSH
     *
     * @param Activity        $activity activity to handle
     * @param Ostatus_profile $oprofile Profile for the feed
     *
     * @return boolean hook value
     */
    function onStartHandleFeedEntryWithProfile($activity, $oprofile, &$notice)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $actor = $oprofile->checkAuthorship($activity);

        if (!$actor instanceof Ostatus_profile) {
            // TRANS: Client exception thrown when no author for an activity was found.
            throw new ClientException(_('Cannot get author for activity.'));
        }

        $object = $activity->objects[0];

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'is_local' => Notice::REMOTE,
                         'source' => 'ostatus');

        // $actor is an ostatus_profile
        $notice = $this->saveNoticeFromActivity($activity, $actor->localProfile(), $options);

        return false;
    }

    /**
     * Handle a posted object from Salmon
     *
     * @param Activity $activity activity to handle
     * @param mixed    $target   user or group targeted
     *
     * @return boolean hook value
     */

    function onStartHandleSalmonTarget($activity, $target)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $this->log(LOG_INFO, "Checking {$activity->id} as a valid Salmon slap.");

        if ($target instanceof User_group) {
            $uri = $target->getUri();
            if (!array_key_exists($uri, $activity->context->attention)) {
                // @todo FIXME: please document (i18n).
                // TRANS: Client exception thrown when ...
                throw new ClientException(_('Object not posted to this group.'));
            }
        } else if ($target instanceof User) {
            $uri      = $target->uri;
            $original = null;
            if (!empty($activity->context->replyToID)) {
                $original = Notice::getKV('uri', $activity->context->replyToID);
            }
            if (!array_key_exists($uri, $activity->context->attention) &&
                (empty($original) ||
                 $original->profile_id != $target->id)) {
                // @todo FIXME: Please document (i18n).
                // TRANS: Client exception when ...
                throw new ClientException(_('Object not posted to this user.'));
            }
        } else {
            // TRANS: Server exception thrown when a micro app plugin uses a target that cannot be handled.
            throw new ServerException(_('Do not know how to handle this kind of target.'));
        }

        $actor = Ostatus_profile::ensureActivityObjectProfile($activity->actor);

        $object = $activity->objects[0];

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'is_local' => Notice::REMOTE,
                         'source' => 'ostatus');

        // $actor is an ostatus_profile
        $this->saveNoticeFromActivity($activity, $actor->localProfile(), $options);

        return false;
    }

    /**
     * Handle object posted via AtomPub
     *
     * @param Activity &$activity Activity that was posted
     * @param User     $user      User that posted it
     * @param Notice   &$notice   Resulting notice
     *
     * @return boolean hook value
     */
    function onStartAtomPubNewActivity(&$activity, $user, &$notice)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $options = array('source' => 'atompub');

        // $user->getProfile() is a Profile
        $notice = $this->saveNoticeFromActivity($activity,
                                                $user->getProfile(),
                                                $options);

        return false;
    }

    /**
     * Handle object imported from a backup file
     *
     * @param User           $user     User to import for
     * @param ActivityObject $author   Original author per import file
     * @param Activity       $activity Activity to import
     * @param boolean        $trusted  Is this a trusted user?
     * @param boolean        &$done    Is this done (success or unrecoverable error)
     *
     * @return boolean hook value
     */
    function onStartImportActivity($user, $author, $activity, $trusted, &$done)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $obj = $activity->objects[0];

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'source' => 'restore');

        // $user->getProfile() is a Profile
        $saved = $this->saveNoticeFromActivity($activity,
                                               $user->getProfile(),
                                               $options);

        if (!empty($saved)) {
            $done = true;
        }

        return false;
    }

    /**
     * Event handler gives the plugin a chance to add custom
     * Atom XML ActivityStreams output from a previously filled-out
     * ActivityObject.
     *
     * The atomOutput method is called if it's one of
     * our matching types.
     *
     * @param ActivityObject $obj
     * @param XMLOutputter $out to add elements at end of object
     * @return boolean hook return value
     */
    function onEndActivityObjectOutputAtom(ActivityObject $obj, XMLOutputter $out)
    {
        if (in_array($obj->type, $this->types())) {
            $this->activityObjectOutputAtom($obj, $out);
        }
        return true;
    }

    /**
     * Event handler gives the plugin a chance to add custom
     * JSON ActivityStreams output from a previously filled-out
     * ActivityObject.
     *
     * The activityObjectOutputJson method is called if it's one of
     * our matching types.
     *
     * @param ActivityObject $obj
     * @param array &$out JSON-targeted array which can be modified
     * @return boolean hook return value
     */
    function onEndActivityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        if (in_array($obj->type, $this->types())) {
            $this->activityObjectOutputJson($obj, $out);
        }
        return true;
    }

    function onStartShowEntryForms(&$tabs)
    {
        $tabs[$this->tag()] = array('title' => $this->appTitle(),
                                    'href'  => common_local_url($this->newFormAction()),
                                   );
        return true;
    }

    function onStartMakeEntryForm($tag, $out, &$form)
    {
        if ($tag == $this->tag()) {
            $form = $this->entryForm($out);
            return false;
        }

        return true;
    }

    function showNotice($notice, $out)
    {
        // TRANS: Server exception thrown when a micro app plugin developer has not done his job too well.
        throw new ServerException(_('You must implement either adaptNoticeListItem() or showNotice().'));
    }
}

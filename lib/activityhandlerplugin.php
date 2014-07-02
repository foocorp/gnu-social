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
 * Superclass for plugins which add Activity types and such
 *
 * @category  Activity
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://gnu.io/social
 */
abstract class ActivityHandlerPlugin extends Plugin
{
    /**
     * Return a list of ActivityStreams object type IRIs
     * which this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams object belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * An empty list means any type is ok. (Favorite verb etc.)
     *
     * All micro-app classes must override this method.
     *
     * @return array of strings
     */
    abstract function types();

    /**
     * Return a list of ActivityStreams verb IRIs which
     * this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams verb belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * All micro-app classes must override this method.
     *
     * @return array of strings
     */
    function verbs() {
        return array(ActivityVerb::POST);
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
    function isMyActivity(Activity $act) {
        return (count($act->objects) == 1
            && ($act->objects[0] instanceof ActivityObject)
            && $this->isMyVerb($act->verb)
            && $this->isMyType($act->objects[0]->type));
    }

    /**
     * Check if a given notice object should be handled by this micro-app
     * plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(). You can override this method to expand
     * your checks, but follow the execution chain to get it right.
     *
     * @param Notice $notice
     * @return boolean
     */
    function isMyNotice(Notice $notice) {
        return $this->isMyVerb($notice->verb) && $this->isMyType($notice->object_type);
    }

    function isMyVerb($verb) {
        $verb = $verb ?: ActivityVerb::POST;    // post is the default verb
        return ActivityUtils::compareTypes($verb, $this->verbs());
    }

    function isMyType($type) {
        return count($this->types())===0 || ActivityUtils::compareTypes($type, $this->types());
    }

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
    abstract function saveNoticeFromActivity(Activity $activity, Profile $actor, array $options=array());

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
    abstract function activityObjectFromNotice(Notice $notice);

    /**
     * When a notice is deleted, you'll be called here for a chance
     * to clean up any related resources.
     *
     * All micro-app classes must override this method.
     *
     * @param Notice $notice
     */
    abstract function deleteRelated(Notice $notice);

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
    function onNoticeDeleteRelated(Notice $notice)
    {
        if (!$this->isMyNotice($notice)) {
            return true;
        }

        $this->deleteRelated($notice);
    }

    /**
     * Render a notice as one of our objects
     *
     * @param Notice         $notice  Notice to render
     * @param ActivityObject &$object Empty object to fill
     *
     * @return boolean hook value
     */
    function onStartActivityObjectFromNotice(Notice $notice, &$object)
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
    function onStartHandleFeedEntryWithProfile(Activity $activity, $oprofile, &$notice)
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

    function onStartHandleSalmonTarget(Activity $activity, $target)
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
    function onStartAtomPubNewActivity(Activity &$activity, $user, &$notice)
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
    function onStartImportActivity($user, $author, Activity $activity, $trusted, &$done)
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
}

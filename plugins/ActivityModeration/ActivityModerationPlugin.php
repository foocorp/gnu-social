<?php

/**
 * @package     Activity
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class ActivityModerationPlugin extends ActivityVerbHandlerPlugin
{
    public function tag()
    {
        return 'actmod';
    }

    public function types()
    {
        return array();
    }

    public function verbs()
    {
        return array(ActivityVerb::DELETE);
    }

    public function onBeforePluginCheckSchema()
    {
        Deleted_notice::beforeSchemaUpdate();
        return true;
    }

    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('deleted_notice', Deleted_notice::schemaDef());
        return true;
    }

    public function onGetNoticeSqlTimestamp($id, &$timestamp)
    {
        try {
            $deleted = Deleted_notice::getByID($id);
            $timestamp = $deleted->act_created;
        } catch (NoResultException $e) {
            return true;
        }
        // we're done for the event, so return false to stop it
        return false;
    }

    public function onIsNoticeDeleted($id, &$deleted)
    {
        try {
            $found = Deleted_notice::getByID($id);
            $deleted = ($found instanceof Deleted_notice);
        } catch (NoResultException $e) {
            $deleted = false;
        }
        // return true (continue event) if $deleted is false, return false (stop event) if deleted notice was found
        return !$deleted;
    }

    protected function getActionTitle(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        // FIXME: switch based on action type
        return _m('TITLE', 'Notice moderation');
    }

    protected function doActionPreparation(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        // pass
    }

    protected function doActionPost(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        switch (true) {
        case ActivityUtils::compareVerbs($verb, array(ActivityVerb::DELETE)):
            // do whatever preparation is necessary to delete a verb
            $target->deleteAs($scoped);
            break;
        default:
            throw new ServerException('ActivityVerb POST not handled by plugin that was supposed to do it.');
        }
    }

    public function deleteRelated(Notice $notice)
    {
        // pass
    }

    public function onDeleteNoticeAsProfile(Notice $stored, Profile $actor, &$result) {
        // By adding a new object with the 'delete' verb we will trigger
        // $this->saveObjectFromActivity that will do the actual ->delete()
        if (false === Deleted_notice::addNew($stored, $actor)) {
            // false is returned if we did not have an error, but did not create the object
            // (i.e. the author is currently being deleted)
            return true;
        }

        // We return false (to stop the event) if the deleted_notice entry was 
        // added, which means we have already run $this->saveObjectFromActivity
        // which in turn has called the delete function of the notice.
        return false;
    }

    /**
     * This is run when a 'delete' verb activity comes in.
     *
     * @return boolean hook flag
     */
    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())
    {
        // Everything is done in the StartNoticeSave event
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

    /**
     * This is run before ->insert, so our task in this function is just to
     * delete if it is the delete verb.
     */ 
    public function onStartNoticeSave(Notice $stored)
    {
        // DELETE is a bit special, we have to remove the existing entry and then
        // add a new one with the same URI in order to trigger the distribution.
        // (that's why we don't use $this->isMyNotice(...))
        if (!ActivityUtils::compareVerbs($stored->verb, array(ActivityVerb::DELETE))) {
            return true;
        }

        try {
            $target = Notice::getByUri($stored->uri);
        } catch (NoResultException $e) {
            throw new AlreadyFulfilledException('Notice URI not found, so we have nothing to delete.');
        }

        $actor = $stored->getProfile();
        $owner = $target->getProfile();

        if ($owner->hasRole(Profile_role::DELETED)) {
            // Don't bother with replacing notices if its author is being deleted.
            // The later "StoreActivityObject" will pick this up and execute
            // the deletion then.
            // (the "delete verb notice" is too new to ever pass through Notice::saveNew
            // which otherwise wouldn't execute the StoreActivityObject event)
            return true;
        }

        // Since the user deleting may not be the same as the notice's owner,
        // double-check this and also set the "re-stored" notice profile_id.
        if (!$actor->sameAs($owner) && !$actor->hasRight(Right::DELETEOTHERSNOTICE)) {
            throw new AuthorizationException(_('You are not allowed to delete another user\'s notice.'));
        }

        // We copy the identifying fields and replace the sensitive ones.
        //$stored->id = $target->id;    // We can't copy this since DB_DataObject won't inject it anyway
        $props = array('uri', 'profile_id', 'conversation', 'reply_to', 'created', 'repeat_of', 'object_type', 'is_local', 'scope');
        foreach($props as $prop) {
            $stored->$prop = $target->$prop;
        }

        // Let's see if this has been deleted already.
        try {
            $deleted = Deleted_notice::getByKeys( ['uri' => $stored->getUri()] );
            return $deleted;
        } catch (NoResultException $e) {
            $deleted = new Deleted_notice();

            $deleted->id            = $target->getID();
            $deleted->profile_id    = $actor->getID();
            $deleted->uri           = $stored->getUri();
            $deleted->act_created   = $stored->created;
            $deleted->created       = common_sql_now();

            // throws exception on error
            $result = $deleted->insert();
        }

        // Now we delete the original notice, leaving the id and uri free.
        $target->delete();

        return true;
    }

    public function extendActivity(Notice $stored, Activity $act, Profile $scoped=null)
    {
        Deleted_notice::extendActivity($stored, $act, $scoped);
    }

    public function activityObjectFromNotice(Notice $notice)
    {
        $object = Deleted_notice::fromStored($notice);
        return $object->asActivityObject();
    }

    protected function getActivityForm(ManagedAction $action, $verb, Notice $target, Profile $scoped) 
    { 
        if (!$scoped instanceof Profile || !($scoped->sameAs($target->getProfile()) || $scoped->hasRight(Right::DELETEOTHERSNOTICE))) {
            throw new AuthorizationException(_('You are not allowed to delete other user\'s notices'));
        }
        return DeletenoticeForm($action, array('notice'=>$target));
    } 
}

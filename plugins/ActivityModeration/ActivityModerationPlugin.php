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
            $target->delete();
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
        // By adding a new 'delete' verb we will eventually trigger $this->saveObjectFromActivity
        if (false === Deleted_notice::addNew($stored, $actor)) {
            // false is returned if we did not have an error, but did not create the object
            // (i.e. the author is currently being deleted)
            return true;
        }

        // We return false (to stop the event) if the deleted_notice entry was 
        // added, which means we have run $this->saveObjectFromActivity which 
        // in turn has called the delete function of the notice.
        return false;
    }

    /**
     * This is run when a 'delete' verb activity comes in.
     *
     * @return boolean hook flag
     */
    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())
    {
        // Let's see if this has been deleted already.
        $deleted = Deleted_notice::getKV('uri', $act->id);
        if ($deleted instanceof Deleted_notice) {
            return $deleted;
        }

        $target = Notice::getByUri($act->objects[0]->id);
        common_debug('DELETING notice: ' . $act->objects[0]->id . ' on behalf of profile id==' . $target->getProfile()->getID());

        $deleted = new Deleted_notice();

        $deleted->id            = $target->getID();
        $deleted->profile_id    = $target->getProfile()->getID();
        $deleted->uri           = $act->id;
        $deleted->act_uri       = $target->getUri();
        $deleted->act_created   = $target->created;
        $deleted->created       = common_sql_now();

        $result = $deleted->insert();
        if ($result === false) {
            throw new ServerException('Could not insert Deleted_notice entry into database!');
        }

        $target->delete();

        return $deleted;
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

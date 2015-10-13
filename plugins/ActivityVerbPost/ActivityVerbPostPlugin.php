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
class ActivityVerbPostPlugin extends ActivityVerbHandlerPlugin
{
    // TODO: Implement a "fallback" feature which can handle anything _as_ an activityobject "note"

    public function tag()
    {
        return 'post';
    }

    public function types()
    {
        return array(ActivityObject::ARTICLE,
                     ActivityObject::BLOGENTRY,
                     ActivityObject::NOTE,
                     ActivityObject::STATUS,
                     ActivityObject::COMMENT,
                    // null,    // if we want to follow the original Ostatus_profile::processActivity code
                    );
    }

    public function verbs()
    {
        return array(ActivityVerb::POST);
    }

    // FIXME: Set this to abstract public in lib/activityhandlerplugin.php when all plugins have migrated!
    protected function saveObjectFromActivity(Activity $act, Notice $stored, array $options=array())
    {
        assert($this->isMyActivity($act));

        $stored->object_type = ActivityUtils::resolveUri($act->objects[0]->type);

        // We don't have to do just about anything for a new, remote notice since the fields
        // are handled in the main Notice::saveActivity function. Such as content, attachments,
        // parent/conversation etc.

        // By returning true here instead of something that evaluates
        // to false, we show that we have processed everything properly.
        return true;
    }

    public function activityObjectFromNotice(Notice $notice)
    {
        $object = new ActivityObject();

        $object->type    = $notice->object_type ?: ActivityObject::NOTE;
        $object->id      = $notice->getUri();
        $object->title   = sprintf('New %1$s by %2$s', ActivityObject::canonicalType($object->type), $notice->getProfile()->getNickname());
        $object->content = $notice->rendered;
        $object->link    = $notice->getUrl();

        $object->extra[] = array('status_net', array('notice_id' => $notice->getID()));

        return $object;
    }

    public function deleteRelated(Notice $notice)
    {
        // No action needed as the table for data storage _is_ the notice table.
        return true;
    }


    /**
     * Command stuff
     */

    // FIXME: Move stuff from lib/command.php to here just as with Share etc.


    /**
     * Layout stuff
     */

    protected function showNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        $out->raw($stored->rendered);
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
        // handle POST
    }

    protected function getActivityForm(ManagedAction $action, $verb, Notice $target, Profile $scoped)
    {
        return new NoticeForm($action, array());
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Post verb',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Post handling with ActivityStreams.'));

        return true;
    }
}

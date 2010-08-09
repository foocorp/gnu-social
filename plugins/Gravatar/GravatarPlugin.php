<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 * @package GravatarPlugin
 * @maintainer Eric Helgeson <erichelgeson@gmail.com>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

class GravatarPlugin extends Plugin
{
    function onInitializePlugin()
    {
        return true;
    }
    
    function onStartAvatarFormData($action)
    {
        $user = common_current_user();
        $hasGravatar = $this->hasGravatar($user->id);
        
        if($hasGravatar) {
            return false;
        }
    }
    
    function onEndAvatarFormData($action)
    {
        $user = common_current_user();
        $hasGravatar = $this->hasGravatar($user->id);

        if(!empty($user->email) && !$hasGravatar) { //and not gravatar already set
            $action->elementStart('form', array('method' => 'post',
                                                'id' => 'form_settings_gravatar_add',
                                                'class' => 'form_settings',
                                                'action' =>
                                                common_local_url('avatarsettings')));
            $action->elementStart('fieldset', array('id' => 'settings_gravatar_add'));
            $action->element('legend', null, _m('Set Gravatar'));
            $action->hidden('token', common_session_token());
            $action->element('p', 'form_guide',
                             _m('If you want to use your Gravatar image, click "Add".'));
            $action->element('input', array('type' => 'submit',
                                            'id' => 'settings_gravatar_add_action-submit',
                                            'name' => 'add',
                                            'class' => 'submit',
                                            'value' => _m('Add')));
            $action->elementEnd('fieldset');
            $action->elementEnd('form');
        } elseif($hasGravatar) {
            $action->elementStart('form', array('method' => 'post',
                                                'id' => 'form_settings_gravatar_remove',
                                                'class' => 'form_settings',
                                                'action' =>
                                                common_local_url('avatarsettings')));
            $action->elementStart('fieldset', array('id' => 'settings_gravatar_remove'));
            $action->element('legend', null, _m('Remove Gravatar'));
            $action->hidden('token', common_session_token());
            $action->element('p', 'form_guide',
                             _m('If you want to remove your Gravatar image, click "Remove".'));
            $action->element('input', array('type' => 'submit',
                                            'id' => 'settings_gravatar_remove_action-submit',
                                            'name' => 'remove',
                                            'class' => 'submit',
                                            'value' => _m('Remove')));
            $action->elementEnd('fieldset');
            $action->elementEnd('form');
        } else {
            $action->element('p', 'form_guide',
                             _m('To use a Gravatar first enter in an email address.'));
        }
    }
    
    function onStartAvatarSaveForm($action)
    {
        if ($action->arg('add')) {
            $result = $this->gravatar_save();

            if($result['success']===true) {
                common_broadcast_profile(common_current_user()->getProfile());
            }

            $action->showForm($result['message'], $result['success']);

            return false;
        } else if ($action->arg('remove')) {
            $result = $this->gravatar_remove();

            if($result['success']===true) {
                common_broadcast_profile(common_current_user()->getProfile());
            }

            $action->showForm($result['message'], $result['success']);

            return false;
        } else {
            return true;
        }
    }

    function hasGravatar($id) {
        $avatar = new Avatar();
        $avatar->profile_id = $id;
        if ($avatar->find()) {
            while ($avatar->fetch()) {
                if($avatar->filename == null) {
                    return true;
                }
            }
        }
        return false;
     }
 

    function gravatar_save()
    {
        $cur = common_current_user();
        
        if(empty($cur->email)) {
            return array('message' => _m('You do not have a email set in your profile.'),
                         'success' => false);
        }
        //Get rid of previous Avatar
        $this->gravatar_remove();
        
        foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
            $gravatar = new Avatar();
            $gravatar->profile_id = $cur->id;
            $gravatar->width = $size;
            $gravatar->height = $size;
            $gravatar->original = false; //No file, so no original
            $gravatar->mediatype = 'img';//XXX: Unsure what to put here
            //$gravatar->filename = null;//No filename. Remote
            $gravatar->url = $this->gravatar_url($cur->email, $size);
            $gravatar->created = DB_DataObject_Cast::dateTime(); # current time

            if (!$gravatar->insert()) {
                return array('message' => _m('Failed to save Gravatar to the DB.'),
                             'success' => false);
            }
        }
        return array('message' => _m('Gravatar added.'),
                     'success' => true);
     }

    function gravatar_remove()
    {
        $user = common_current_user();
        $profile = $user->getProfile();

        $avatar = $profile->getOriginalAvatar();
        if($avatar) $avatar->delete();
        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        if($avatar) $avatar->delete();
        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        if($avatar) $avatar->delete();
        $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);
        if($avatar) $avatar->delete();

        return array('message' => _m('Gravatar removed.'),
                     'success' => true);
    }
 
    function gravatar_url($email, $size)
    {
        $url = "http://www.gravatar.com/avatar.php?gravatar_id=".
                md5(strtolower($email)).
                "&default=".urlencode(Avatar::defaultImage($size)).
                "&size=".$size;
            return $url;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Gravatar',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Eric Helgeson',
                            'homepage' => 'http://status.net/wiki/Plugin:Gravatar',
                            'rawdescription' =>
                            _m('The Gravatar plugin allows users to use their <a href="http://www.gravatar.com/">Gravatar</a> with StatusNet.'));

        return true;
    }
}
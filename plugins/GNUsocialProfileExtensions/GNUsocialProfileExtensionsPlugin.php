<?php
/**
 * GNU Social
 * Copyright (C) 2010, Free Software Foundation, Inc.
 *
 * PHP version 5
 *
 * LICENCE:
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
 * @category  Widget
 * @package   GNU Social
 * @author    Max Shinn <trombonechamp@gmail.com>
 * @copyright 2011 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class GNUsocialProfileExtensionsPlugin extends Plugin
{

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'BioAction':
        case 'NewresponseAction':
            include_once $dir . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            break;
        case 'ProfilefieldsAdminPanelAction':
            include_once $dir . '/actions/' . strtolower(mb_substr($cls, 0, -16)) . '.php';
            break;
        default:
            break;
        }
        include_once $dir . '/classes/GNUsocialProfileExtensionField.php';
        include_once $dir . '/classes/GNUsocialProfileExtensionResponse.php';
        include_once $dir . '/lib/profiletools.php';
        include_once $dir . '/lib/noticetree.php';
        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('GNUsocialProfileExtensionField', GNUsocialProfileExtensionField::schemaDef());
        $schema->ensureTable('GNUsocialProfileExtensionResponse', GNUsocialProfileExtensionResponse::schemaDef());
                                          
    }

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/bio', array('action' => 'bio'));
        $m->connect('admin/profilefields', array('action' => 'profilefieldsAdminPanel'));
        $m->connect('notice/respond', array('action' => 'newresponse'));
        return true;
    }

    function onEndProfileFormData($action)
    {
        $fields = GNUsocialProfileExtensionField::allFields();
        $user = common_current_user();
        $profile = $user->getProfile();
        gnusocial_profile_merge($profile);
        foreach ($fields as $field) {
            $action->elementStart('li');
            $fieldname = $field->systemname;
            if ($field->type == 'str') {
                $action->input($fieldname, $field->title, 
                               ($action->arg($fieldname)) ? $action->arg($fieldname) : $profile->$fieldname, 
                               $field->description);
            }
            else if ($field->type == 'text') {
                $action->textarea($fieldname, $field->title,
                                  ($action->arg($fieldname)) ? $action->arg($fieldname) : $profile->$fieldname,
                                  $field->description);
            }
            $action->elementEnd('li');
        }
    }

    function onEndProfileSaveForm($action)
    {
        $fields = GNUsocialProfileExtensionField::allFields();
        $user = common_current_user();
        $profile = $user->getProfile();
        foreach ($fields as $field) {
            $val = $action->trimmed($field->systemname);

            $response = new GNUsocialProfileExtensionResponse();
            $response->profile_id = $profile->id;
            $response->extension_id = $field->id;
            
            if ($response->find()) {
                $response->fetch();
                $response->value = $val;
                if ($response->validate()) {
                    if (empty($val))
                        $response->delete();
                    else
                        $response->update();
                }
            }
            else {
                $response->value = $val;
                $response->insert();
            }
        }
    }
    
    function onEndShowStyles($action)
    {
        $action->cssLink('/plugins/GNUsocialProfileExtensions/res/style.css');
    }

    function onEndShowScripts($action)
    {
        $action->script('plugins/GNUsocialProfileExtensions/js/profile.js');
    }

    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('profilefields')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(
                '/admin/profilefields',
                _m('Profile Fields'),
                _m('Custom profile fields'),
                $action_name == 'profilefieldsadminpanel',
                'nav_profilefields_admin_panel'
            );
        }

        return true;
    }

    function onStartPersonalGroupNav($nav)
    { 
        $nav->out->menuItem(common_local_url('bio',
                           array('nickname' => $nav->action->trimmed('nickname'))), _('Bio'), 
                           _('The user\'s extended profile'), $nav->action->trimmed('action') == 'bio', 'nav_bio');
    }

    //Why the heck is this shoved into this plugin!?!?  It deserves its own!
    function onShowStreamNoticeList($notice, $action, &$pnl)
    {
        //TODO: This function is called after the notices in $notice are superfluously retrieved in showstream.php
        $newnotice = new Notice();
        $newnotice->profile_id = $action->user->id;
        $newnotice->orderBy('modified DESC');
        $newnotice->whereAdd('reply_to IS NULL');
        $newnotice->limit(($action->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        $newnotice->find();

        $pnl = new NoticeTree($newnotice, $action);
        return false;
    }

}


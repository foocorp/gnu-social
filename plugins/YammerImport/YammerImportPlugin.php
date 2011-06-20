<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @package YammerImportPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) { exit(1); }

class YammerImportPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('panel/yammer',
                    array('action' => 'yammeradminpanel'));
        $m->connect('panel/yammer/auth',
                    array('action' => 'yammerauth'));
        return true;
    }

    /**
     * Set up queue handlers for import processing
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        $qm->connect('yammer', 'YammerQueueHandler');

        return true;
    }

    /**
     * Set up all our tables...
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $tables = array('Yammer_state',
                        'Yammer_user',
                        'Yammer_group',
                        'Yammer_notice',
                        'Yammer_notice_stub');
        foreach ($tables as $table) {
            $schemaDef = call_user_func(array($table, 'schemaDef'));
            $schema->ensureTable(strtolower($table), $schemaDef);
        }

        return true;
    }

    /**
     * If the plugin's installed, this should be accessible to admins.
     */
    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'yammer') {
            $isOK = true;
            return false;
        }

        return true;
    }

    /**
     * Add the Yammer admin panel to the list...
     */
    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('yammer')) {
            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(common_local_url('yammeradminpanel'),
                                // TRANS: Menu item for Yammer import.
                                _m('MENU','Yammer'),
                                // TRANS: Menu item title for Yammer import.
                                _m('Yammer import module.'),
                                $action_name == 'yammeradminpanel',
                                'nav_yammer_admin_panel');
        }

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        switch ($lower) {
        case 'sn_yammerclient':
        case 'yammerimporter':
        case 'yammerrunner':
        case 'yammerapikeyform':
        case 'yammerauthinitform':
        case 'yammerauthverifyform':
        case 'yammerprogressform':
        case 'yammerqueuehandler':
            require_once "$base/lib/$lower.php";
            return false;
        case 'yammeradminpanelaction':
            $crop = substr($lower, 0, strlen($lower) - strlen('action'));
            require_once "$base/actions/$crop.php";
            return false;
        case 'yammer_state':
        case 'yammer_notice_stub':
        case 'yammer_common':
        case 'yammer_user':
        case 'yammer_group':
        case 'yammer_notice':
            require_once "$base/classes/$cls.php";
            return false;
        default:
            return true;
        }
    }
}

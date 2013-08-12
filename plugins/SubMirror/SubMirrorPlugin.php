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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * @package SubMirrorPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class SubMirrorPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('settings/mirror',
                    array('action' => 'mirrorsettings'));
        $m->connect('settings/mirror/add/:provider',
                    array('action' => 'mirrorsettings'),
                    array('provider' => '[A-Za-z0-9_-]+'));
        $m->connect('settings/mirror/add',
                    array('action' => 'addmirror'));
        $m->connect('settings/mirror/edit',
                    array('action' => 'editmirror'));
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
        $files = array("$base/lib/$lower.php",
                       "$base/classes/$cls.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    function handle($notice)
    {
        // Is anybody mirroring?
        $mirror = new SubMirror();
        $mirror->subscribed = $notice->profile_id;
        if ($mirror->find()) {
            while ($mirror->fetch()) {
                $mirror->repeat($notice);
            }
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'SubMirror',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:SubMirror',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Pull feeds into your timeline!'));

        return true;
    }

    /**
     * Menu item for personal subscriptions/groups area
     *
     * @param Action $action action being executed
     *
     * @return boolean hook return
     */
    function onEndAccountSettingsNav($action)
    {
        $action_name = $action->trimmed('action');

        common_debug("ACTION NAME = " . $action_name);

        $action->menuItem(common_local_url('mirrorsettings'),
                          // TRANS: SubMirror plugin menu item on user settings page.
                          _m('MENU', 'Mirroring'),
                          // TRANS: SubMirror plugin tooltip for user settings menu item.
                          _m('Configure mirroring of posts from other feeds'),
                          $action_name === 'mirrorsettings');

        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('submirror', SubMirror::schemaDef());

        // @hack until key definition support is merged
        SubMirror::fixIndexes($schema);
        return true;
    }

    /**
     * Set up queue handlers for outgoing hub pushes
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        // After each notice save, check if there's any repeat mirrors.
        $qm->connect('mirror', 'MirrorQueueHandler');
        return true;
    }

    function onStartEnqueueNotice($notice, &$transports)
    {
        $transports[] = 'mirror';
    }

    /**
     * Let the OStatus subscription garbage collection know if we're
     * making use of a remote feed, so it doesn't get dropped out
     * from under us.
     *
     * @param Ostatus_profile $oprofile
     * @param int $count in/out
     * @return mixed hook return value
     */
    function onOstatus_profileSubscriberCount($oprofile, &$count)
    {
        if (empty($oprofile) || !($oprofile instanceof Ostatus_profile)) {
            return true;
        }

        if ($oprofile->profile_id) {
            $mirror = new SubMirror();
            $mirror->subscribed = $oprofile->profile_id;
            if ($mirror->find()) {
                while ($mirror->fetch()) {
                    $count++;
                }
            }
        }

        return true;
    }

    /**
     * Add a count of mirrored feeds into a user's profile sidebar stats.
     *
     * @param Profile $profile
     * @param array $stats
     * @return boolean hook return value
     */
    function onProfileStats($profile, &$stats)
    {
        $cur = common_current_user();
        if (!empty($cur) && $cur->id == $profile->id) {
            $mirror = new SubMirror();
            $mirror->subscriber = $profile->id;
            $entry = array(
                'id' => 'mirrors',
                // TRANS: Label in profile statistics section, followed by a count.
                'label' => _m('Mirrored feeds'),
                'link' => common_local_url('mirrorsettings'),
                'value' => $mirror->count(),
            );

            $insertAt = count($stats);
            foreach ($stats as $i => $row) {
                if ($row['id'] == 'groups') {
                    // Slip us in after them.
                    $insertAt = $i + 1;
                    break;
                }
            }
            array_splice($stats, $insertAt, 0, array($entry));
        }
        return true;
    }
}

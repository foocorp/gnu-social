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
 * @package GroupFavoritedPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) { exit(1); }

class GroupFavoritedPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param URLMapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized(URLMapper $m)
    {
        $m->connect('group/:nickname/favorited',
                    array('action' => 'groupfavorited'),
                    array('nickname' => '[a-zA-Z0-9]+'));

        return true;
    }

    function onEndGroupGroupNav(Menu $nav)
    {
        $action_name = $nav->action->trimmed('action');
        $nickname = $nav->group->nickname;
        $nav->out->menuItem(common_local_url('groupfavorited', array('nickname' =>
                                                                     $nickname)),
                            // TRANS: Menu item in the group navigation page.
                            _m('MENU', 'Popular'),
                            // TRANS: Tooltip for menu item in the group navigation page.
                            // TRANS: %s is the nickname of the group.
                            sprintf(_m('TOOLTIP','Popular notices in %s group'), $nickname),
                            $action_name == 'groupfavorited',
                            'nav_group_group');
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(array &$versions)
    {
        $url = 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/GroupFavorited';

        $versions[] = array('name' => 'GroupFavorited',
            'version' => GNUSOCIAL_VERSION,
            'author' => 'Brion Vibber',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('This plugin adds a menu item for popular notices in groups.'));

        return true;
    }
}

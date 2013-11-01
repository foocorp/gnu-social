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
 * @package SlicedFavoritesPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) { exit(1); }

class SlicedFavoritesPlugin extends Plugin
{
    /**
     * Example:
     *
     *   addPlugin('SlicedFavorites', array(
     *     'slices' => array(
     *       // show only pop's notices on /favorited
     *       'default' => array('include' => array('pop')),
     *
     *       // show only son's notices on /favorited/blog
     *       'blog' => array('include' => array('son')),
     *
     *       // show all favorited notices except pop's and son's on /favorited/submitted
     *       'submitted' => array('exclude' => array('pop', 'son')),
     *
     *       // show all favorited notices on /favorited/everybody
     *       'everybody' => array(),
     *     )
     *   ));
     *
     * @var array
     */
    public $slices = array();

    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('favorited/:slice',
                    array('action' => 'favoritedslice'),
                    array('slice' => '[a-zA-Z0-9]+'));

        return true;
    }

    // Take over the default... :D
    function onArgsInitialize($args)
    {
        if (array_key_exists('action', $args)) {
            $action = trim($args['action']);
            if ($action == 'favorited') {
                common_redirect(common_local_url('favoritedslice', array('slice' => 'default')));
                exit(0);
            }
        }
        return true;
    }

    function onSlicedFavoritesGetSettings($slice, &$data)
    {
        if (isset($this->slices[$slice])) {
            $data = $this->slices[$slice];
            return false;
        }
        return true;
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
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:SlicedFavorites';

        $versions[] = array('name' => 'SlicedFavorites',
            'version' => GNUSOCIAL_VERSION,
            'author' => 'Brion Vibber',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('Shows timelines of popular notices for defined subsets of users.'));

        return true;
    }
}

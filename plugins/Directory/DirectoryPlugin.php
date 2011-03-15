<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Adds a user directory
 *
 * PHP version 5
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
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copely <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Directory plugin main class
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DirectoryPlugin extends Plugin
{

    private $dir = null;

    /**
     * Initializer for this plugin
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function initialize()
    {
        return true;
    }

    /**
     * Cleanup for this plugin.
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function cleanup()
    {
        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function onAutoload($cls)
    {
        // common_debug("class = $cls");

        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'UserdirectoryAction':
            include_once $dir
                . '/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'AlphaNav':
            include_once $dir
                . '/lib/' . strtolower($cls) . '.php';
            return false;
        case 'SortableSubscriptionList':
            include_once $dir
                . '/lib/' . strtolower($cls) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function onRouterInitialized($m)
    {
        $m->connect(
            'directory/users',
            array('action' => 'userdirectory'),
            array('filter' => 'all')
        );

        $m->connect(
            'directory/users/:filter',
            array('action' => 'userdirectory'),
            array('filter' => '([0-9a-zA-Z_]{1,64}|0-9)')
        );

        return true;
    }

    /**
     * Link in a styelsheet for the onboarding actions
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    function onEndShowStatusNetStyles($action)
    {
        if (in_array(
            $action->trimmed('action'),
            array('userdirectory'))
        ) {
            $action->cssLink($this->path('css/directory.css'));
        }

        return true;
    }

    /**
     * Modify the public local nav to add a link to the user directory
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     *
     * @see Action
     */
    function onEndPublicGroupNav($nav)
    {
        // XXX: Maybe this should go under search instead?

        $actionName = $nav->action->trimmed('action');

        $nav->out->menuItem(
            common_local_url('userdirectory'),
            _('Directory'),
            _('User Directory'),
            $actionName == 'userdirectory',
            'nav_directory'
        );

        return true;
    }

    /*
     * Version info
     */
    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name' => 'Directory',
            'version' => STATUSNET_VERSION,
            'author' => 'Zach Copley',
            'homepage' => 'http://status.net/wiki/Plugin:Directory',
            'rawdescription' => _m('Add a user directory.')
        );

        return true;
    }
}

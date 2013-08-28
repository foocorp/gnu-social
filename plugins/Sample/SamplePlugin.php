<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
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
 * @category  Sample
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Sample plugin main class
 *
 * Each plugin requires a main class to interact with the StatusNet system.
 *
 * The main class usually extends the Plugin class that comes with StatusNet.
 *
 * The class has standard-named methods that will be called when certain events
 * happen in the code base. These methods have names like 'onX' where X is an
 * event name (see EVENTS.txt for the list of available events). Event handlers
 * have pre-defined arguments, based on which event they're handling. A typical
 * event handler:
 *
 *    function onSomeEvent($paramA, &$paramB)
 *    {
 *        if ($paramA == 'jed') {
 *            throw new Exception(sprintf(_m("Invalid parameter %s"), $paramA));
 *        }
 *        $paramB = 'spock';
 *        return true;
 *    }
 *
 * Event handlers must return a boolean value. If they return false, all other
 * event handlers for this event (in other plugins) will be skipped, and in some
 * cases the default processing for that event would be skipped. This is great for
 * replacing the default action of an event.
 *
 * If the handler returns true, processing of other event handlers and the default
 * processing will continue. This is great for extending existing functionality.
 *
 * If the handler throws an exception, processing will stop, and the exception's
 * error will be shown to the user.
 *
 * To install a plugin (like this one), site admins add the following code to
 * their config.php file:
 *
 *     addPlugin('Sample');
 *
 * Plugins must be installed in one of the following directories:
 *
 *     local/plugins/{$pluginclass}.php
 *     local/plugins/{$name}/{$pluginclass}.php
 *     local/{$pluginclass}.php
 *     local/{$name}/{$pluginclass}.php
 *     plugins/{$pluginclass}.php
 *     plugins/{$name}/{$pluginclass}.php
 *
 * Here, {$name} is the name of the plugin, like 'Sample', and {$pluginclass} is
 * the name of the main class, like 'SamplePlugin'. Plugins that are part of the
 * main StatusNet distribution go in 'plugins' and third-party or local ones go
 * in 'local'.
 *
 * Simple plugins can be implemented as a single module. Others are more complex
 * and require additional modules; these should use their own directory, like
 * 'local/plugins/{$name}/'. All files related to the plugin, including images,
 * JavaScript, CSS, external libraries or PHP modules should go in the plugin
 * directory.
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SamplePlugin extends Plugin
{
    /**
     * Plugins are configured using public instance attributes. To set
     * their values, site administrators use this syntax:
     *
     * addPlugin('Sample', array('attr1' => 'foo', 'attr2' => 'bar'));
     *
     * The same plugin class can be initialized multiple times with different
     * arguments:
     *
     * addPlugin('EmailNotify', array('sendTo' => 'evan@status.net'));
     * addPlugin('EmailNotify', array('sendTo' => 'brionv@status.net'));
     *
     */

    public $attr1 = null;
    public $attr2 = null;

    /**
     * Initializer for this plugin
     *
     * Plugins overload this method to do any initialization they need,
     * like connecting to remote servers or creating paths or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {
        return true;
    }

    /**
     * Cleanup for this plugin
     *
     * Plugins overload this method to do any cleanup they need,
     * like disconnecting from remote servers or deleting temp files or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function cleanup()
    {
        return true;
    }

    /**
     * Database schema setup
     *
     * Plugins can add their own tables to the StatusNet database. Plugins
     * should use StatusNet's schema interface to add or delete tables. The
     * ensureTable() method provides an easy way to ensure a table's structure
     * and availability.
     *
     * By default, the schema is checked every time StatusNet is run (say, when
     * a Web page is hit). Admins can configure their systems to only check the
     * schema when the checkschema.php script is run, greatly improving performance.
     * However, they need to remember to run that script after installing or
     * upgrading a plugin!
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles
        $schema->ensureTable('user_greeting_count', User_greeting_count::schemaDef());
        return true;
    }

    /**
     * Map URLs to actions
     *
     * This event handler lets the plugin map URLs on the site to actions (and
     * thus an action handler class). Note that the action handler class for an
     * action will be named 'FoobarAction', where action = 'foobar'. The class
     * must be loaded in the onAutoload() method.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        $m->connect('main/hello',
                    array('action' => 'hello'));
        return true;
    }

    /**
     * Modify the default menu to link to our custom action
     *
     * Using event handlers, it's possible to modify the default UI for pages
     * almost without limit. In this method, we add a menu item to the default
     * primary menu for the interface to link to our action.
     *
     * The Action class provides a rich set of events to hook, as well as output
     * methods.
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */
    function onEndPrimaryNav($action)
    {
        // common_local_url() gets the correct URL for the action name
        // we provide
        $action->menuItem(common_local_url('hello'),
                          // TRANS: Menu item in sample plugin.
                          _m('Hello'),
                          // TRANS: Menu item title in sample plugin.
                          _m('A warm greeting'), false, 'nav_hello');
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Sample',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber, Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Sample',
                            'rawdescription' =>
                          // TRANS: Plugin description.
                            _m('A sample plugin to show basics of development for new hackers.'));
        return true;
    }
}

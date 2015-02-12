<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * utilities for defining and running event handlers
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for events
 *
 * This "class" two static functions for managing events in the StatusNet code.
 *
 * @category Event
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @todo     Define a system for using Event instances
 */

class Event {

    /* Global array of hooks, mapping eventname => array of callables */

    protected static $_handlers = array();

    /**
     * Add an event handler
     *
     * To run some code at a particular point in StatusNet processing.
     * Named events include receiving an XMPP message, adding a new notice,
     * or showing part of an HTML page.
     *
     * The arguments to the handler vary by the event. Handlers can return
     * two possible values: false means that the event has been replaced by
     * the handler completely, and no default processing should be done.
     * Non-false means successful handling, and that the default processing
     * should succeed. (Note that this only makes sense for some events.)
     *
     * Handlers can also abort processing by throwing an exception; these will
     * be caught by the closest code and displayed as errors.
     *
     * @param string   $name    Name of the event
     * @param callable $handler Code to run
     *
     * @return void
     */

    public static function addHandler($name, $handler) {
        if (array_key_exists($name, Event::$_handlers)) {
            Event::$_handlers[$name][] = $handler;
        } else {
            Event::$_handlers[$name] = array($handler);
        }
    }

    /**
     * Handle an event
     *
     * Events are any point in the code that we want to expose for admins
     * or third-party developers to use.
     *
     * We pass in an array of arguments (including references, for stuff
     * that can be changed), and each assigned handler gets run with those
     * arguments. Exceptions can be thrown to indicate an error.
     *
     * @param string $name Name of the event that's happening
     * @param array  $args Arguments for handlers
     *
     * @return boolean flag saying whether to continue processing, based
     *                 on results of handlers.
     */

    public static function handle($name, array $args=array()) {
        $result = null;
        if (array_key_exists($name, Event::$_handlers)) {
            foreach (Event::$_handlers[$name] as $handler) {
                $result = call_user_func_array($handler, $args);
                if ($result === false) {
                    break;
                }
            }
        }
        return ($result !== false);
    }

    /**
     * Check to see if an event handler exists
     *
     * Look to see if there's any handler for a given event, or narrow
     * by providing the name of a specific plugin class.
     *
     * @param string $name Name of the event to look for
     * @param string $plugin Optional name of the plugin class to look for
     *
     * @return boolean flag saying whether such a handler exists
     *
     */

    public static function hasHandler($name, $plugin=null) {
        if (array_key_exists($name, Event::$_handlers)) {
            if (isset($plugin)) {
                foreach (Event::$_handlers[$name] as $handler) {
                    if (get_class($handler[0]) == $plugin) {
                        return true;
                    }
                }
            } else {
                return true;
            }
        }
        return false;
    }

    public static function getHandlers($name)
    {
        return Event::$_handlers[$name];
    }

    /**
     * Disables any and all handlers that have been set up so far;
     * use only if you know it's safe to reinitialize all plugins.
     */
    public static function clearHandlers() {
        Event::$_handlers = array();
    }
}

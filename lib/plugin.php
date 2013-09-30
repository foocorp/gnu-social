<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility class for plugins
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
 * @category  Plugin
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
 * Base class for plugins
 *
 * A base class for StatusNet plugins. Mostly a light wrapper around
 * the Event framework.
 *
 * Subclasses of Plugin will automatically handle an event if they define
 * a method called "onEventName". (Well, OK -- only if they call parent::__construct()
 * in their constructors.)
 *
 * They will also automatically handle the InitializePlugin and CleanupPlugin with the
 * initialize() and cleanup() methods, respectively.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */

class Plugin
{
    function __construct()
    {
        Event::addHandler('InitializePlugin', array($this, 'initialize'));
        Event::addHandler('CleanupPlugin', array($this, 'cleanup'));

        foreach (get_class_methods($this) as $method) {
            if (mb_substr($method, 0, 2) == 'on') {
                Event::addHandler(mb_substr($method, 2), array($this, $method));
            }
        }

        $this->setupGettext();
    }

    function initialize()
    {
        return true;
    }

    function cleanup()
    {
        return true;
    }

    /**
     * Load related modules when needed
     *
     * Most non-trivial plugins will require extra modules to do their work. Typically
     * these include data classes, action classes, widget classes, or external libraries.
     *
     * This method receives a class name and loads the PHP file related to that class. By
     * tradition, action classes typically have files named for the action, all lower-case.
     * Data classes are in files with the data class name, initial letter capitalized.
     *
     * Note that this method will be called for *all* overloaded classes, not just ones
     * in this plugin! So, make sure to return true by default to let other plugins, and
     * the core code, get a chance.
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onAutoload($cls) {
        $cls = basename($cls);
        $basedir = INSTALLDIR . '/plugins/' . mb_substr(get_called_class(), 0, -6);
        $file = null;

        if (preg_match('/^(\w+)(Action|Form)$/', $cls, $type)) {
            $type = array_map('strtolower', $type);
            $file = "$basedir/{$type[2]}s/{$type[1]}.php";
        }
        if (!file_exists($file)) {
            $file = "$basedir/classes/{$cls}.php";

            // library files can be put into subdirs ('_'->'/' conversion)
            // such as LRDDMethod_WebFinger -> lib/lrddmethod/webfinger.php
            if (!file_exists($file)) {
                $type = strtolower($cls);
                $type = str_replace('_', '/', $type);
                $file = "$basedir/lib/{$type}.php";
            }
        }

        if (!is_null($file) && file_exists($file)) {
            require_once($file);
            return false;
        }

        return true;
    }

    /**
     * Checks if this plugin has localization that needs to be set up.
     * Gettext localizations can be called via the _m() helper function.
     */
    protected function setupGettext()
    {
        $class = get_class($this);
        if (substr($class, -6) == 'Plugin') {
            $name = substr($class, 0, -6);
            $path = common_config('plugins', 'locale_path');
            if (!$path) {
                // @fixme this will fail for things installed in local/plugins
                // ... but then so will web links so far.
                $path = INSTALLDIR . "/plugins/$name/locale";
            }
            if (file_exists($path) && is_dir($path)) {
                bindtextdomain($name, $path);
                bind_textdomain_codeset($name, 'UTF-8');
            }
        }
    }

    protected function log($level, $msg)
    {
        common_log($level, get_class($this) . ': '.$msg);
    }

    protected function debug($msg)
    {
        $this->log(LOG_DEBUG, $msg);
    }
    
    function name()
    {
        $cls = get_class($this);
        return mb_substr($cls, 0, -6);
    }

    function onPluginVersion(&$versions)
    {
        $name = $this->name();

        $versions[] = array('name' => $name,
                            // TRANS: Displayed as version information for a plugin if no version information was found.
                            'version' => _('Unknown'));

        return true;
    }

    function path($relative)
    {
        return self::staticPath($this->name(), $relative);
    }

    static function staticPath($plugin, $relative)
    {
        $isHTTPS = StatusNet::isHTTPS();

        if ($isHTTPS) {
            $server = common_config('plugins', 'sslserver');
        } else {
            $server = common_config('plugins', 'server');
        }

        if (empty($server)) {
            if ($isHTTPS) {
                $server = common_config('site', 'sslserver');
            }
            if (empty($server)) {
                $server = common_config('site', 'server');
            }
        }

        if ($isHTTPS) {
            $path = common_config('plugins', 'sslpath');
        } else {
            $path = common_config('plugins', 'path');
        }

        if (empty($path)) {
            // XXX: extra stat().
            if (@file_exists(INSTALLDIR.'/local/plugins/'.$plugin.'/'.$relative)) {
                $path = common_config('site', 'path') . '/local/plugins/';
            } else {
                $path = common_config('site', 'path') . '/plugins/';
            }
        }

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        $protocol = ($isHTTPS) ? 'https' : 'http';

        return $protocol.'://'.$server.$path.$plugin.'/'.$relative;
    }
}

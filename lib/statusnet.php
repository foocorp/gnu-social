<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010 StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

global $config, $_server, $_path;

/**
 * Global configuration setup and management.
 */
class StatusNet
{
    protected static $have_config;
    protected static $is_api;
    protected static $is_ajax;
    protected static $plugins = array();

    /**
     * Configure and instantiate a plugin into the current configuration.
     * Class definitions will be loaded from standard paths if necessary.
     * Note that initialization events won't be fired until later.
     *
     * @param string $name class name & plugin file/subdir name
     * @param array $attrs key/value pairs of public attributes to set on plugin instance
     *
     * @throws ServerException if plugin can't be found
     */
    public static function addPlugin($name, array $attrs=array())
    {
        $name = ucfirst($name);

        if (isset(self::$plugins[$name])) {
            // We have already loaded this plugin. Don't try to
            // do it again with (possibly) different values.
            // Försten till kvarn får mala.
            return true;
        }

        $pluginclass = "{$name}Plugin";

        if (!class_exists($pluginclass)) {

            $files = array("local/plugins/{$pluginclass}.php",
                           "local/plugins/{$name}/{$pluginclass}.php",
                           "local/{$pluginclass}.php",
                           "local/{$name}/{$pluginclass}.php",
                           "plugins/{$pluginclass}.php",
                           "plugins/{$name}/{$pluginclass}.php");

            foreach ($files as $file) {
                $fullpath = INSTALLDIR.'/'.$file;
                if (@file_exists($fullpath)) {
                    include_once($fullpath);
                    break;
                }
            }
            if (!class_exists($pluginclass)) {
                throw new ServerException("Plugin $name not found.", 500);
            }
        }

        // Doesn't this $inst risk being garbage collected or something?
        // TODO: put into a static array that makes sure $inst isn't lost.
        $inst = new $pluginclass();
        foreach ($attrs as $aname => $avalue) {
            $inst->$aname = $avalue;
        }

        // Record activated plugins for later display/config dump
        self::$plugins[$name] = $attrs;

        return true;
    }

    /**
     * Get a list of activated plugins in this process.
     * @return array of (string $name, array $args) pairs
     */
    public static function getActivePlugins()
    {
        return self::$plugins;
    }

    /**
     * Initialize, or re-initialize, StatusNet global configuration
     * and plugins.
     *
     * If switching site configurations during script execution, be
     * careful when working with leftover objects -- global settings
     * affect many things and they may not behave as you expected.
     *
     * @param $server optional web server hostname for picking config
     * @param $path optional URL path for picking config
     * @param $conffile optional configuration file path
     *
     * @throws NoConfigException if config file can't be found
     */
    public static function init($server=null, $path=null, $conffile=null)
    {
        Router::clear();

        self::initDefaults($server, $path);
        self::loadConfigFile($conffile);

        $sprofile = common_config('site', 'profile');
        if (!empty($sprofile)) {
            self::loadSiteProfile($sprofile);
        }
        // Load settings from database; note we need autoload for this
        Config::loadSettings();

        self::initPlugins();
    }

    /**
     * Get identifier of the currently active site configuration
     * @return string
     */
    public static function currentSite()
    {
        return common_config('site', 'nickname');
    }

    /**
     * Change site configuration to site specified by nickname,
     * if set up via Status_network. If not, sites other than
     * the current will fail horribly.
     *
     * May throw exception or trigger a fatal error if the given
     * site is missing or configured incorrectly.
     *
     * @param string $nickname
     */
    public static function switchSite($nickname)
    {
        if ($nickname == StatusNet::currentSite()) {
            return true;
        }

        $sn = Status_network::getKV('nickname', $nickname);
        if (empty($sn)) {
            return false;
            throw new Exception("No such site nickname '$nickname'");
        }

        $server = $sn->getServerName();
        StatusNet::init($server);
    }

    /**
     * Pull all local sites from status_network table.
     *
     * Behavior undefined if site is not configured via Status_network.
     *
     * @return array of nicknames
     */
    public static function findAllSites()
    {
        $sites = array();
        $sn = new Status_network();
        $sn->find();
        while ($sn->fetch()) {
            $sites[] = $sn->nickname;
        }
        return $sites;
    }

    /**
     * Fire initialization events for all instantiated plugins.
     */
    protected static function initPlugins()
    {
        // User config may have already added some of these plugins, with
        // maybe configured parameters. The self::addPlugin function will
        // ignore the new call if it has already been instantiated.

        // Load core plugins
        foreach (common_config('plugins', 'core') as $name => $params) {
            call_user_func('self::addPlugin', $name, $params);
        }

        // Load default plugins
        foreach (common_config('plugins', 'default') as $name => $params) {
            $key = 'disable-' . $name;
            if (common_config('plugins', $key)) {
                continue;
            }

            // TODO: We should be able to avoid this is_null and assume $params
            // is an array, since that's how it is typed in addPlugin
            if (is_null($params)) {
                self::addPlugin($name);
            } else if (is_array($params)) {
                if (count($params) == 0) {
                    self::addPlugin($name);
                } else {
                    $keys = array_keys($params);
                    if (is_string($keys[0])) {
                        self::addPlugin($name, $params);
                    } else {
                        foreach ($params as $paramset) {
                            self::addPlugin($name, $paramset);
                        }
                    }
                }
            }
        }

        // XXX: if plugins should check the schema at runtime, do that here.
        if (common_config('db', 'schemacheck') == 'runtime') {
            Event::handle('CheckSchema');
        }

        // Give plugins a chance to initialize in a fully-prepared environment
        Event::handle('InitializePlugin');
    }

    /**
     * Quick-check if configuration has been established.
     * Useful for functions which may get used partway through
     * initialization to back off from fancier things.
     *
     * @return bool
     */
    public static function haveConfig()
    {
        return self::$have_config;
    }

    public static function isApi()
    {
        return self::$is_api;
    }

    public static function setApi($mode)
    {
        self::$is_api = $mode;
    }

    public static function isAjax()
    {
        return self::$is_ajax;
    }

    public static function setAjax($mode)
    {
        self::$is_ajax = $mode;
    }

    /**
     * Build default configuration array
     * @return array
     */
    protected static function defaultConfig()
    {
        global $_server, $_path;
        require(INSTALLDIR.'/lib/default.php');
        return $default;
    }

    /**
     * Establish default configuration based on given or default server and path
     * Sets global $_server, $_path, and $config
     */
    public static function initDefaults($server, $path)
    {
        global $_server, $_path, $config, $_PEAR;

        Event::clearHandlers();
        self::$plugins = array();

        // try to figure out where we are. $server and $path
        // can be set by including module, else we guess based
        // on HTTP info.

        if (isset($server)) {
            $_server = $server;
        } else {
            $_server = array_key_exists('SERVER_NAME', $_SERVER) ?
              strtolower($_SERVER['SERVER_NAME']) :
            null;
        }

        if (isset($path)) {
            $_path = $path;
        } else {
            $_path = (array_key_exists('SERVER_NAME', $_SERVER) && array_key_exists('SCRIPT_NAME', $_SERVER)) ?
              self::_sn_to_path($_SERVER['SCRIPT_NAME']) :
            null;
        }

        // Set config values initially to default values
        $default = self::defaultConfig();
        $config = $default;

        // default configuration, overwritten in config.php
        // Keep DB_DataObject's db config synced to ours...

        $config['db'] = &$_PEAR->getStaticProperty('DB_DataObject','options');

        $config['db'] = $default['db'];

        if (function_exists('date_default_timezone_set')) {
            /* Work internally in UTC */
            date_default_timezone_set('UTC');
        }
    }

    public static function loadSiteProfile($name)
    {
        global $config;
        $settings = SiteProfile::getSettings($name);
        $config = array_replace_recursive($config, $settings);
    }

    protected static function _sn_to_path($sn)
    {
        $past_root = substr($sn, 1);
        $last_slash = strrpos($past_root, '/');
        if ($last_slash > 0) {
            $p = substr($past_root, 0, $last_slash);
        } else {
            $p = '';
        }
        return $p;
    }

    /**
     * Load the default or specified configuration file.
     * Modifies global $config and may establish plugins.
     *
     * @throws NoConfigException
     */
    protected static function loadConfigFile($conffile=null)
    {
        global $_server, $_path, $config;

        // From most general to most specific:
        // server-wide, then vhost-wide, then for a path,
        // finally for a dir (usually only need one of the last two).

        if (isset($conffile)) {
            $config_files = array($conffile);
        } else {
            $config_files = array('/etc/statusnet/statusnet.php',
                                  '/etc/statusnet/laconica.php',
                                  '/etc/laconica/laconica.php',
                                  '/etc/statusnet/'.$_server.'.php',
                                  '/etc/laconica/'.$_server.'.php');

            if (strlen($_path) > 0) {
                $config_files[] = '/etc/statusnet/'.$_server.'_'.$_path.'.php';
                $config_files[] = '/etc/laconica/'.$_server.'_'.$_path.'.php';
            }

            $config_files[] = INSTALLDIR.'/config.php';
        }

        self::$have_config = false;

        foreach ($config_files as $_config_file) {
            if (@file_exists($_config_file)) {
                // Ignore 0-byte config files
                if (filesize($_config_file) > 0) {
                    common_log(LOG_INFO, "Including config file: " . $_config_file);
                    include($_config_file);
                    self::$have_config = true;
                }
            }
        }

        if (!self::$have_config) {
            throw new NoConfigException("No configuration file found.",
                                        $config_files);
        }

        // Check for database server; must exist!

        if (empty($config['db']['database'])) {
            throw new ServerException("No database server for this site.");
        }
    }

    /**
     * Are we running from the web with HTTPS?
     *
     * @return boolean true if we're running with HTTPS; else false
     */

    static function isHTTPS()
    {
        // There are some exceptions to this; add them here!
        if(empty($_SERVER['HTTPS'])) {
            return false;
        } else {
            return $_SERVER['HTTPS'] !== 'off';
        }
    }
}

class NoConfigException extends Exception
{
    public $configFiles;

    function __construct($msg, $configFiles) {
        parent::__construct($msg);
        $this->configFiles = $configFiles;
    }
}

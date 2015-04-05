<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utilities for theme files and paths
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
 * @category  Paths
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for querying and manipulating a theme
 *
 * Themes are directories with some expected sub-directories and files
 * in them. They're found in either local/theme (for locally-installed themes)
 * or theme/ subdir of installation dir.
 *
 * Note that the 'local' directory can be overridden as $config['local']['path']
 * and $config['local']['dir'] etc.
 *
 * This used to be a couple of functions, but for various reasons it's nice
 * to have a class instead.
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Theme
{
    const FALLBACK = 'neo';

    var $name = null;
    var $dir  = null;
    var $path = null;
    protected $metadata = null; // access via getMetadata() lazy-loader
    protected $externals = null;
    protected $deps = null;

    /**
     * Constructor
     *
     * Determines the proper directory and path for this theme.
     *
     * @param string $name Name of the theme; defaults to config value
     */
    function __construct($name=null)
    {
        if (empty($name)) {
            $name = common_config('site', 'theme');
        }
        if (!self::validName($name)) {
            // TRANS: Server exception displayed if a theme name was invalid.
            throw new ServerException(_('Invalid theme name.'));
        }
        $this->name = $name;

        // Check to see if it's in the local dir

        $localroot = self::localRoot();

        $fulldir = $localroot.'/'.$name;

        if (file_exists($fulldir) && is_dir($fulldir)) {
            $this->dir  = $fulldir;
            $this->path = $this->relativeThemePath('local', 'local', 'theme/' . $name);
            return;
        }

        // Check to see if it's in the distribution dir

        $instroot = self::installRoot();

        $fulldir = $instroot.'/'.$name;

        if (file_exists($fulldir) && is_dir($fulldir)) {
            $this->dir = $fulldir;
            $this->path = $this->relativeThemePath('theme', 'theme', $name);
            return;
        }

        // Ruh roh. Fall back to default, then.

        common_log(LOG_WARNING, sprintf("Unable to find theme '%s', falling back to default theme '%s'",
                                        $name,
                                        Theme::FALLBACK));

        $this->name = Theme::FALLBACK;
        $this->dir  = $instroot.'/'.Theme::FALLBACK;
        $this->path = $this->relativeThemePath('theme', 'theme', Theme::FALLBACK);

    }

    /**
     * Build a full URL to the given theme's base directory, possibly
     * using an offsite theme server path.
     *
     * @param string $group configuration section name to pull paths from
     * @param string $fallbackSubdir default subdirectory under INSTALLDIR
     * @param string $name theme name
     *
     * @return string URL
     *
     * @todo consolidate code with that for other customizable paths
     */
    protected function relativeThemePath($group, $fallbackSubdir, $name)
    {
        if (GNUsocial::isHTTPS()) {
            $sslserver = common_config($group, 'sslserver');

            if (empty($sslserver)) {
                if (is_string(common_config('site', 'sslserver')) &&
                    mb_strlen(common_config('site', 'sslserver')) > 0) {
                    $server = common_config('site', 'sslserver');
                } else if (common_config('site', 'server')) {
                    $server = common_config('site', 'server');
                }
                $path   = common_config('site', 'path') . '/';
                if ($fallbackSubdir) {
                    $path .= $fallbackSubdir . '/';
                }
            } else {
                $server = $sslserver;
                $path   = common_config($group, 'sslpath');
                if (empty($path)) {
                    $path = common_config($group, 'path');
                }
            }

            $protocol = 'https';
        } else {
            $path = common_config($group, 'path');

            if (empty($path)) {
                $path = common_config('site', 'path') . '/';
                if ($fallbackSubdir) {
                    $path .= $fallbackSubdir . '/';
                }
            }

            $server = common_config($group, 'server');

            if (empty($server)) {
                $server = common_config('site', 'server');
            }

            $protocol = 'http';
        }

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        return $protocol.'://'.$server.$path.$name;
    }

    /**
     * Gets the full local filename of a file in this theme.
     *
     * @param string $relative relative name, like 'logo.png'
     *
     * @return string full pathname, like /var/www/mublog/theme/default/logo.png
     */
    function getFile($relative)
    {
        return $this->dir.'/'.$relative;
    }

    /**
     * Gets the full HTTP url of a file in this theme
     *
     * @param string $relative relative name, like 'logo.png'
     *
     * @return string full URL, like 'http://example.com/theme/default/logo.png'
     */
    function getPath($relative)
    {
        return $this->path.'/'.$relative;
    }

    /**
     * Fetch a list of other themes whose CSS needs to be pulled in before
     * this theme's, based on following the theme.ini 'include' settings.
     * (May be empty if this theme has no include dependencies.)
     *
     * @return array of strings with theme names
     */
    function getDeps()
    {
        if ($this->deps === null) {
            $chain = $this->doGetDeps(array($this->name));
            array_pop($chain); // Drop us back off
            $this->deps = $chain;
        }
        return $this->deps;
    }

    protected function doGetDeps($chain)
    {
        $data = $this->getMetadata();
        if (!empty($data['include'])) {
            $include = $data['include'];

            // Protect against cycles!
            if (!in_array($include, $chain)) {
                try {
                    $theme = new Theme($include);
                    array_unshift($chain, $include);
                    return $theme->doGetDeps($chain);
                } catch (Exception $e) {
                    common_log(LOG_ERR,
                            "Exception while fetching theme dependencies " .
                            "for $this->name: " . $e->getMessage());
                }
            }
        }
        return $chain;
    }

    /**
     * Pull data from the theme's theme.ini file.
     * @fixme calling getFile will fall back to default theme, this may be unsafe.
     *
     * @return associative array of strings
     */
    function getMetadata()
    {
        if ($this->metadata == null) {
            $this->metadata = $this->doGetMetadata();
        }
        return $this->metadata;
    }

    /**
     * Pull data from the theme's theme.ini file.
     * @fixme calling getFile will fall back to default theme, this may be unsafe.
     *
     * @return associative array of strings
     */
    private function doGetMetadata()
    {
        $iniFile = $this->getFile('theme.ini');
        if (file_exists($iniFile)) {
            return parse_ini_file($iniFile);
        } else {
            return array();
        }
    }

    /**
     * Get list of any external URLs required by this theme and any
     * dependencies. These are lazy-loaded from theme.ini.
     *
     * @return array of URL strings
     */
    function getExternals()
    {
        if ($this->externals == null) {
            $data = $this->getMetadata();
            if (!empty($data['external'])) {
                $ext = (array)$data['external'];
            } else {
                $ext = array();
            }

            if (!empty($data['include'])) {
                $theme = new Theme($data['include']);
                $ext = array_merge($ext, $theme->getExternals());
            }

            $this->externals = array_unique($ext);
        }
        return $this->externals;
    }

    /**
     * Gets the full path of a file in a theme dir based on its relative name
     *
     * @param string $relative relative path within the theme directory
     * @param string $name     name of the theme; defaults to current theme
     *
     * @return string File path to the theme file
     */
    static function file($relative, $name=null)
    {
        $theme = new Theme($name);
        return $theme->getFile($relative);
    }

    /**
     * Gets the full URL of a file in a theme dir based on its relative name
     *
     * @param string $relative relative path within the theme directory
     * @param string $name     name of the theme; defaults to current theme
     *
     * @return string URL of the file
     */
    static function path($relative, $name=null)
    {
        $theme = new Theme($name);
        return $theme->getPath($relative);
    }

    /**
     * list available theme names
     *
     * @return array list of available theme names
     */
    static function listAvailable()
    {
        $local   = self::subdirsOf(self::localRoot());
        $install = self::subdirsOf(self::installRoot());

        $i = array_search('base', $install);

        unset($install[$i]);

        return array_merge($local, $install);
    }

    /**
     * Utility for getting subdirs of a directory
     *
     * @param string $dir full path to directory to check
     *
     * @return array relative filenames of subdirs, or empty array
     */
    protected static function subdirsOf($dir)
    {
        $subdirs = array();

        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($filename = readdir($dh)) !== false) {
                    if ($filename != '..' && $filename !== '.' &&
                        is_dir($dir.'/'.$filename)) {
                        $subdirs[] = $filename;
                    }
                }
                closedir($dh);
            }
        }

        return $subdirs;
    }

    /**
     * Local root dir for themes
     *
     * @return string local root dir for themes
     */
    protected static function localRoot()
    {
        $basedir = common_config('local', 'dir');

        if (empty($basedir)) {
            $basedir = INSTALLDIR . '/local';
        }

        return $basedir . '/theme';
    }

    /**
     * Root dir for themes that are shipped with StatusNet
     *
     * @return string root dir for StatusNet themes
     */
    protected static function installRoot()
    {
        $instroot = common_config('theme', 'dir');

        if (empty($instroot)) {
            $instroot = INSTALLDIR.'/theme';
        }

        return $instroot;
    }

    static function validName($name)
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $name);
    }
}

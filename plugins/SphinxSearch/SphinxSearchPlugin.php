<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

// Set defaults if not already set in the config array...
global $config;
$sphinxDefaults =
    array('enabled' => true,
          'server' => 'localhost',
          'port' => 3312);
foreach($sphinxDefaults as $key => $val) {
    if (!isset($config['sphinx'][$key])) {
        $config['sphinx'][$key] = $val;
    }
}

/**
 * Plugin for Sphinx search backend.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 * @link     http://twitter.com/
 */
class SphinxSearchPlugin extends Plugin
{
    /**
     * Automatically load any classes used
     *
     * @param string $cls the class
     * @return boolean hook return
     */
    function onAutoload($cls)
    {
        switch ($cls) {
        case 'SphinxSearch':
            include_once INSTALLDIR . '/plugins/SphinxSearch/' .
              strtolower($cls) . '.php';
            return false;
        }

        return parent::onAutoload($cls);
    }

    /**
     * Create sphinx search engine object for the given table type.
     *
     * @param Memcached_DataObject $target
     * @param string $table
     * @param out &$search_engine SearchEngine object on output if successful
     * @ return boolean hook return
     */
    function onGetSearchEngine(Memcached_DataObject $target, $table, &$search_engine)
    {
        if (common_config('sphinx', 'enabled')) {
            if (!class_exists('SphinxClient')) {
                // TRANS: Server exception.
                throw new ServerException(_m('Sphinx PHP extension must be installed.'));
            }
            $engine = new SphinxSearch($target, $table);
            if ($engine->is_connected()) {
                $search_engine = $engine;
                return false;
            }
        }
        // Sphinx disabled or disconnected
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
        $url = 'http://status.net/wiki/Plugin:SphinxSearch';

        $versions[] = array('name' => 'SphinxSearch',
            'version' => GNUSOCIAL_VERSION,
            'author' => 'Brion Vibber',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('Plugin for Sphinx search backend.'));

        return true;
    }
}

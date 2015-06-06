<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Logs cache access
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
 * @category  Cache
 * @package   StatusNet
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
 * Log cache access
 *
 * Note that since most caching plugins return false for StartCache*
 * methods, you should add this plugin before them, i.e.
 *
 *     addPlugin('CacheLog');
 *     addPlugin('XCache');
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class CacheLogPlugin extends Plugin
{
    function onStartCacheGet(&$key, &$value)
    {
        $this->log(LOG_INFO, "Fetching key '$key'");
        return true;
    }

    function onEndCacheGet($key, &$value)
    {
        if ($value === false) {
            $this->log(LOG_INFO, sprintf('Cache MISS for key "%s"', $key));
        } else {
            $this->log(LOG_INFO, sprintf('Cache HIT for key "%s": %s', $key, self::showValue($value)));
        }
        return true;
    }

    function onStartCacheSet(&$key, &$value, &$flag, &$expiry, &$success)
    {
        $this->log(LOG_INFO, "Begin setting cache value for key '$key'");
        return true;
    }

    function onEndCacheSet($key, $value, $flag, $expiry)
    {
        $this->log(LOG_INFO, sprintf('Set cache value %s for key "%s" (flags: %d, expiry %d)',
                                     self::showValue($value),
                                     $key,
                                     $flag,
                                     $expiry));
        return true;
    }

    function onStartCacheDelete(&$key, &$success)
    {
        $this->log(LOG_INFO, "Deleting cache value for key '$key'");
        return true;
    }

    function onEndCacheDelete($key)
    {
        $this->log(LOG_INFO, "Done deleting cache value for key '$key'");
        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'CacheLog',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:CacheLog',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Log reads and writes to the cache.'));
        return true;
    }

    static function showValue($value)
    {
        if (is_object($value)) {
            return sprintf('object of class %s', get_class($value));
        } else if (is_array($value)) {
            return sprintf('array of length %d', count($value));
        } else if (is_string($value)) {
            return sprintf('string "%s"', $value);
        } else if (is_integer($value)) {
            return sprintf('integer %d', $value);
        } else if (is_null($value)) {
            return 'null';
        } else {
            return 'unknown';
        }
    }
}

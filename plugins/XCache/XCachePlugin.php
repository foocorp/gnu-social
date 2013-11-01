<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Plugin to implement cache interface for XCache variable cache
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
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A plugin to use XCache's variable cache for the cache interface
 *
 * New plugin interface lets us use alternative cache systems
 * for caching. This one uses XCache's variable cache.
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
class XCachePlugin extends Plugin
{
    /**
     * Get a value associated with a key
     *
     * The value should have been set previously.
     *
     * @param string &$key   in; Lookup key
     * @param mixed  &$value out; value associated with key
     *
     * @return boolean hook success
     */
    function onStartCacheGet(&$key, &$value)
    {
        if (!xcache_isset($key)) {
            $value = false;
        } else {
            $value = xcache_get($key);
            $value = unserialize($value);
        }
        Event::handle('EndCacheGet', array($key, &$value));
        return false;
    }

    /**
     * Associate a value with a key
     *
     * @param string  &$key     in; Key to use for lookups
     * @param mixed   &$value   in; Value to associate
     * @param integer &$flag    in; Flag (passed through to Memcache)
     * @param integer &$expiry  in; Expiry (passed through to Memcache)
     * @param boolean &$success out; Whether the set was successful
     *
     * @return boolean hook success
     */
    function onStartCacheSet(&$key, &$value, &$flag, &$expiry, &$success)
    {
        $success = xcache_set($key, serialize($value));

        Event::handle('EndCacheSet', array($key, $value, $flag,
                                           $expiry));
        return false;
    }

    /**
     * Delete a value associated with a key
     *
     * @param string  &$key     in; Key to lookup
     * @param boolean &$success out; whether it worked
     *
     * @return boolean hook success
     */
    function onStartCacheDelete(&$key, &$success)
    {
        $success = xcache_unset($key);
        Event::handle('EndCacheDelete', array($key));
        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'XCache',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:XCache',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Use the <a href="http://xcache.lighttpd.net/">XCache</a> variable cache to cache query results.'));
        return true;
    }
}

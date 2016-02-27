<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Plugin to implement cache interface with disk files
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
 * A plugin to cache data on local disk
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
class DiskCachePlugin extends Plugin
{
    var $root = '/tmp';

    function keyToFilename($key)
    {
        return $this->root . '/' . str_replace(':', '/', $key);
    }

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
        $filename = $this->keyToFilename($key);

        if (file_exists($filename)) {
            $data = file_get_contents($filename);
            if ($data !== false) {
                $value = unserialize($data);
            }
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
        $filename = $this->keyToFilename($key);
        $parent = dirname($filename);

        $sofar = '';

        foreach (explode('/', $parent) as $part) {
            if (empty($part)) {
                continue;
            }
            $sofar .= '/' . $part;
            if (!is_dir($sofar)) {
                $this->debug("Creating new directory '$sofar'");
                $success = mkdir($sofar, 0750);
                if (!$success) {
                    $this->log(LOG_ERR, "Can't create directory '$sofar'");
                    return false;
                }
            }
        }

        if (is_dir($filename)) {
            $success = false;
            return false;
        }

        // Write to a temp file and move to destination

        $tempname = tempnam(null, 'statusnetdiskcache');

        $result = file_put_contents($tempname, serialize($value));

        if ($result === false) {
            $this->log(LOG_ERR, "Couldn't write '$key' to temp file '$tempname'");
            return false;
        }

        $result = rename($tempname, $filename);

        if (!$result) {
            $this->log(LOG_ERR, "Couldn't move temp file '$tempname' to path '$filename' for key '$key'");
            @unlink($tempname);
            return false;
        }

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
        $filename = $this->keyToFilename($key);

        if (file_exists($filename) && !is_dir($filename)) {
            unlink($filename);
        }

        Event::handle('EndCacheDelete', array($key));
        return false;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'DiskCache',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/DiskCache',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to implement cache interface with disk files.'));
        return true;
    }
}

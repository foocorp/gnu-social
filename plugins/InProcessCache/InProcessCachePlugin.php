<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Extra level of caching, in memory
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Extra level of caching
 *
 * This plugin adds an extra level of in-process caching to any regular
 * cache system like APC, XCache, or Memcache.
 *
 * Note that since most caching plugins return false for StartCache*
 * methods, you should add this plugin before them, i.e.
 *
 *     addPlugin('InProcessCache');
 *     addPlugin('XCache');
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class InProcessCachePlugin extends Plugin
{
    private $_items = array();
    private $_hits  = array();
    private $active;

    /**
     * Constructor checks if it's safe to use the in-process cache.
     * On CLI scripts, we'll disable ourselves to avoid data corruption
     * due to keeping stale data around.
     *
     * On web requests we'll roll the dice; they're short-lived so have
     * less chance of stale data. Race conditions are still possible,
     * so beware!
     */
    function __construct()
    {
        parent::__construct();
        $this->active = (PHP_SAPI != 'cli');
    }

    /**
     * Get an item from the cache
     *
     * Called before other cache systems are called (iif this
     * plugin was loaded correctly, see class comment). If we
     * have the data, return it, and don't hit the other cache
     * systems.
     *
     * @param string &$key   Key to fetch
     * @param mixed  &$value Resulting value or false for miss
     *
     * @return boolean false if found, else true
     */
    function onStartCacheGet(&$key, &$value)
    {
        if ($this->active && array_key_exists($key, $this->_items)) {
            $value = $this->_items[$key];
            if (array_key_exists($key, $this->_hits)) {
                $this->_hits[$key]++;
            } else {
                $this->_hits[$key] = 1;
            }
            Event::handle('EndCacheGet', array($key, &$value));
            return false;
        }
        return true;
    }

    /**
     * Called at the end of a cache get
     *
     * If we don't already have the data, we cache it. This
     * keeps us from having to call the external cache if the
     * key is requested again.
     *
     * @param string $key    Key to fetch
     * @param mixed  &$value Resulting value or false for miss
     *
     * @return boolean hook value, true
     */
    function onEndCacheGet($key, &$value)
    {
        if ($this->active && (!array_key_exists($key, $this->_items) ||
            $this->_items[$key] != $value)) {
            $this->_items[$key] = $value;
        }
        return true;
    }

    /**
     * Called at the end of setting a cache element
     *
     * Always set the cache element; may overwrite existing
     * data.
     *
     * @param string  $key    Key to fetch
     * @param mixed   $value  Resulting value or false for miss
     * @param integer $flag   ignored
     * @param integer $expiry ignored
     *
     * @return boolean true
     */
    function onEndCacheSet($key, $value, $flag, $expiry)
    {
        if ($this->active) {
            $this->_items[$key] = $value;
        }
        return true;
    }

    /**
     * Called at the end of deleting a cache element
     *
     * If stuff's deleted from the other cache, we
     * delete it too.
     *
     * @param string  &$key     Key to delete
     * @param boolean &$success Success flag; ignored
     *
     * @return boolean true
     */
    function onStartCacheDelete(&$key, &$success)
    {
        if ($this->active && array_key_exists($key, $this->_items)) {
            unset($this->_items[$key]);
        }
        return true;
    }

    /**
     * Version info
     *
     * @param array &$versions Array of version blocks
     *
     * @return boolean true
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:InProcessCache';

        $versions[] = array('name' => 'InProcessCache',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => $url,
                            'description' =>
                            // TRANS: Plugin dscription.
                            _m('Additional in-process cache for plugins.'));
        return true;
    }

    /**
     * Cleanup function; called at end of process
     *
     * If the inprocess/stats config value is true, we dump
     * stats to the log file
     *
     * @return boolean true
     */
    function cleanup()
    {
        if ($this->active && common_config('inprocess', 'stats')) {
            $this->log(LOG_INFO, "cache size: " .
                       count($this->_items));
            $sum = 0;
            foreach ($this->_hits as $hitcount) {
                $sum += $hitcount;
            }
            $this->log(LOG_INFO, $sum . " hits on " .
                       count($this->_hits) . " keys");
        }
        return true;
    }
}

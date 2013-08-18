<?php
/**
 * Data class for homepage blacklisting
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Data class for Homepage blacklist
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Homepage_blacklist extends Managed_DataObject
{
    public $__table = 'homepage_blacklist'; // table name
    public $pattern;                        // string pattern
    public $created;                        // datetime

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */
    function table()
    {
        return array('pattern' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */
    function keyTypes()
    {
        return array('pattern' => 'K');
    }

    /**
     * Return a list of patterns to check
     *
     * @return array string patterns to check
     */
    static function getPatterns()
    {
        $patterns = self::cacheGet('homepage_blacklist:patterns');

        if ($patterns === false) {

            $patterns = array();

            $nb = new Homepage_blacklist();

            $nb->find();

            while ($nb->fetch()) {
                $patterns[] = $nb->pattern;
            }

            self::cacheSet('homepage_blacklist:patterns', $patterns);
        }

        return $patterns;
    }

    /**
     * Save new list of patterns
     *
     * @return array of patterns to check
     */
    static function saveNew($newPatterns)
    {
        $oldPatterns = self::getPatterns();

        // Delete stuff that's old that not in new
        $toDelete = array_diff($oldPatterns, $newPatterns);

        // Insert stuff that's in new and not in old
        $toInsert = array_diff($newPatterns, $oldPatterns);

        foreach ($toDelete as $pattern) {
            $nb = Homepage_blacklist::staticGet('pattern', $pattern);
            if (!empty($nb)) {
                $nb->delete();
            }
        }

        foreach ($toInsert as $pattern) {
            $nb = new Homepage_blacklist();
            $nb->pattern = $pattern;
            $nb->created = common_sql_now();
            $nb->insert();
        }

        self::blow('homepage_blacklist:patterns');
    }

    static function ensurePattern($pattern)
    {
        $hb = Homepage_blacklist::staticGet('pattern', $pattern);

        if (empty($nb)) {
            $hb = new Homepage_blacklist();
            $hb->pattern = $pattern;
            $hb->created = common_sql_now();
            $hb->insert();
            self::blow('homepage_blacklist:patterns');
        }
    }
}

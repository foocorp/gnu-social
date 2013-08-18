<?php
/**
 * Data class for counting user registrations by date
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
 * Copyright (C) 2010, StatusNet, Inc.
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
 * Data class for counting users by date
 *
 * We make a separate sitemap for each user registered by date.
 * To save ourselves some processing effort, we cache this data
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Sitemap_user_count extends Managed_DataObject
{
    public $__table = 'sitemap_user_count'; // table name

    public $registration_date;               // date primary_key not_null
    public $user_count;                      // int(4)
    public $created;
    public $modified;

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
        return array('registration_date' => DB_DATAOBJECT_DATE + DB_DATAOBJECT_NOTNULL,
                     'user_count' => DB_DATAOBJECT_INT,
                     'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified'  => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
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
        return array('registration_date' => 'K');
    }

    function sequenceKey()
    {
        return array(false, false, false);
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
        return $this->keys();
    }

    static function getAll()
    {
        $userCounts = self::cacheGet('sitemap:user:counts');

        if ($userCounts === false) {

            $suc = new Sitemap_user_count();
            $suc->orderBy('registration_date DESC');

            // Fetch the first one to check up-to-date-itude

            $n = $suc->find(true);

            $today = self::today();
            $userCounts = array();

            if (!$n) { // No counts saved yet
                $userCounts = self::initializeCounts();
            } else if ($suc->registration_date < $today) { // There are counts but not up to today
                $userCounts = self::fillInCounts($suc->registration_date);
            } else if ($suc->registration_date == $today) { // Refresh today's
                $userCounts[$today] = self::updateToday();
            }

            // starts with second-to-last date

            while ($suc->fetch()) {
                $userCounts[$suc->registration_date] = $suc->user_count;
            }

            // Cache user counts for 4 hours.

            self::cacheSet('sitemap:user:counts', $userCounts, null, time() + 4 * 60 * 60);
        }

        return $userCounts;
    }

    static function initializeCounts()
    {
        $firstDate = self::getFirstDate(); // awww
        $today     = self::today();

        $counts = array();

        for ($d = $firstDate; $d <= $today; $d = self::incrementDay($d)) {
            $n = self::getCount($d);
            self::insertCount($d, $n);
            $counts[$d] = $n;
        }

        return $counts;
    }

    static function fillInCounts($lastDate)
    {
        $today = self::today();

        $counts = array();

        $n = self::getCount($lastDate);
        self::updateCount($lastDate, $n);

        $counts[$lastDate] = $n;

        for ($d = self::incrementDay($lastDate); $d <= $today; $d = self::incrementDay($d)) {
            $n = self::getCount($d);
            self::insertCount($d, $n);
        }

        return $counts;
    }

    static function updateToday()
    {
        $today = self::today();

        $n = self::getCount($today);
        self::updateCount($today, $n);

        return $n;
    }

    static function getCount($d)
    {
        $user = new User();
        $user->whereAdd('created BETWEEN "'.$d.' 00:00:00" AND "'.self::incrementDay($d).' 00:00:00"');
        $n = $user->count();

        return $n;
    }

    static function insertCount($d, $n)
    {
        $suc = new Sitemap_user_count();

        $suc->registration_date = DB_DataObject_Cast::date($d);
        $suc->user_count        = $n;
        $suc->created           = common_sql_now();
        $suc->modified          = $suc->created;

        if (!$suc->insert()) {
            common_log(LOG_WARNING, "Could not save user counts for '$d'");
        }
    }

    static function updateCount($d, $n)
    {
        $suc = Sitemap_user_count::getKV('registration_date', DB_DataObject_Cast::date($d));

        if (empty($suc)) {
            // TRANS: Exception thrown when a registration date cannot be found.
            throw new Exception(_m("No such registration date: $d."));
        }

        $orig = clone($suc);

        $suc->registration_date = DB_DataObject_Cast::date($d);
        $suc->user_count        = $n;
        $suc->created           = common_sql_now();
        $suc->modified          = $suc->created;

        if (!$suc->update($orig)) {
            common_log(LOG_WARNING, "Could not save user counts for '$d'");
        }
    }

    static function incrementDay($d)
    {
        $dt = self::dateStrToInt($d);
        return self::dateIntToStr($dt + 24 * 60 * 60);
    }

    static function dateStrToInt($d)
    {
        return strtotime($d.' 00:00:00');
    }

    static function dateIntToStr($dt)
    {
        return date('Y-m-d', $dt);
    }

    static function getFirstDate()
    {
        $u = new User();
        $u->selectAdd();
        $u->selectAdd('date(min(created)) as first_date');
        if ($u->find(true)) {
            return $u->first_date;
        } else {
            // Is this right?
            return self::dateIntToStr(time());
        }
    }

    static function today()
    {
        return self::dateIntToStr(time());
    }
}

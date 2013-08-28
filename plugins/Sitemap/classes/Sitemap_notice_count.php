<?php
/**
 * Data class for counting notice postings by date
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
 * Data class for counting notices by date
 *
 * We make a separate sitemap for each notice posted by date.
 * To save ourselves some (not inconsiderable) processing effort,
 * we cache this data in the sitemap_notice_count table. Each
 * row represents a day since the site has been started, with a count
 * of notices posted on that day. Since, after the end of the day,
 * this number doesn't change, it's a good candidate for persistent caching.
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Sitemap_notice_count extends Managed_DataObject
{
    public $__table = 'sitemap_notice_count'; // table name

    public $notice_date;                       // date primary_key not_null
    public $notice_count;                      // int(4)
    public $created;                           // datetime()   not_null
    public $modified;                          // datetime   not_null default_0000-00-00%2000%3A00%3A00

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_date' => array('type' => 'date', 'not null' => true, 'description' => 'record date'),
                'notice_count' => array('type' => 'int', 'not null' => true, 'description' => 'the notice count'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_date'),
        );
    }

    static function getAll()
    {
        $noticeCounts = self::cacheGet('sitemap:notice:counts');

        if ($noticeCounts === false) {
            $snc = new Sitemap_notice_count();
            $snc->orderBy('notice_date DESC');

            // Fetch the first one to check up-to-date-itude

            $n = $snc->find(true);

            $today = self::today();
            $noticeCounts = array();

            if (!$n) { // No counts saved yet
                $noticeCounts = self::initializeCounts();
            } else if ($snc->notice_date < $today) { // There are counts but not up to today
                $noticeCounts = self::fillInCounts($snc->notice_date);
            } else if ($snc->notice_date == $today) { // Refresh today's
                $noticeCounts[$today] = self::updateToday();
            }

            // starts with second-to-last date

            while ($snc->fetch()) {
                $noticeCounts[$snc->notice_date] = $snc->notice_count;
            }

            // Cache notice counts for 4 hours.

            self::cacheSet('sitemap:notice:counts', $noticeCounts, null, time() + 4 * 60 * 60);
        }

        return $noticeCounts;
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
        $notice = new Notice();
        $notice->whereAdd('created BETWEEN "'.$d.' 00:00:00" AND "'.self::incrementDay($d).' 00:00:00"');
        $notice->whereAdd('is_local = ' . Notice::LOCAL_PUBLIC);
        $n = $notice->count();

        return $n;
    }

    static function insertCount($d, $n)
    {
        $snc = new Sitemap_notice_count();

        $snc->notice_date = DB_DataObject_Cast::date($d);

        $snc->notice_count      = $n;
        $snc->created           = common_sql_now();
        $snc->modified          = $snc->created;

        if (!$snc->insert()) {
            common_log(LOG_WARNING, "Could not save user counts for '$d'");
        }
    }

    static function updateCount($d, $n)
    {
        $snc = Sitemap_notice_count::getKV('notice_date', DB_DataObject_Cast::date($d));

        if (empty($snc)) {
            // TRANS: Exception
            throw new Exception(_m("No such registration date: $d."));
        }

        $orig = clone($snc);

        $snc->notice_date = DB_DataObject_Cast::date($d);

        $snc->notice_count      = $n;
        $snc->created           = common_sql_now();
        $snc->modified          = $snc->created;

        if (!$snc->update($orig)) {
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
        $n = new Notice();

        $n->selectAdd();
        $n->selectAdd('date(min(created)) as first_date');

        if ($n->find(true)) {
            return $n->first_date;
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

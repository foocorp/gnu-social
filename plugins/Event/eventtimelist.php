<?php
/**
 * Helper class for calculating and displaying event times
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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

/**
 *  Class to get fancy times for the dropdowns on the new event form
 */
class EventTimeList {
    /**
     * Round up to the nearest half hour
     *
     * @param string $time the time to round (date/time string)
     * @return DateTime    the rounded time
     */
    public static function nearestHalfHour($time)
    {
        $startd = new DateTime($time);

        $minutes = $startd->format('i');
        $hour    = $startd->format('H');

        if ($minutes >= 30) {
            $minutes = '00';
            $hour++;
        } else {
            $minutes = '30';
        }

        $startd->setTime($hour, $minutes, 0);

        return $startd;
    }

    /**
     * Output a list of times in half-hour intervals
     *
     * @param string  $start       Time to start with (date string, usually a ts)
     * @param boolean $duration    Whether to include the duration of the event
     *                             (from the start)
     * @return array  $times (localized 24 hour time string => fancy time string)
     */
    public static function getTimes($start = 'now', $duration = false)
    {
        $newTime = new DateTime($start);
        $times   = array();
        $len     = 0;

        $newTime->setTimezone(new DateTimeZone(common_timezone()));

        for ($i = 0; $i < 47; $i++) {

            $localTime = $newTime->format("g:ia");

            // pretty up the end-time option list a bit
            if ($duration) {
                $hours = $len / 60;
                switch ($hours) {
                case 0:
                    // TRANS: 0 minutes abbreviated. Used in a list.
                    $total = ' ' . _m('(0 min)');
                    break;
                case .5:
                    // TRANS: 30 minutes abbreviated. Used in a list.
                    $total = ' ' . _m('(30 min)');
                    break;
                case 1:
                    // TRANS: 1 hour. Used in a list.
                    $total = ' ' . _m('(1 hour)');
                    break;
                default:
                    // TRANS: Number of hours (%.1f and %d). Used in a list.
                    $format = is_float($hours) 
                        ? _m('(%.1f hours)')
                        : _m('(%d hours)');
                    $total = ' ' . sprintf($format, $hours);
                    break;
                }
                $localTime .= $total;
                $len += 30;
            }

            $times[$newTime->format('g:ia')] = $localTime;
            $newTime->modify('+30min'); // 30 min intervals
        }

        return $times;
    }
}

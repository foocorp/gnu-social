<?php
/*
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Check DB queries for filesorts and such and log em.
 *
 * @package SQLStatsPlugin
 * @maintainer Evan Prodromou <evan@status.net>
 */
class SQLStatsPlugin extends Plugin
{
    protected $queryCount = 0;
    protected $queryStart = 0;
    protected $queryTimes = array();
    protected $queries    = array();

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'SQLStats',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/SQLStats',
                            'rawdescription' =>
                            // TRANS: Plugin decription.
                            _m('Debug tool to watch for poorly indexed DB queries.'));

        return true;
    }

    function onStartDBQuery($obj, $query, &$result)
    {
        $this->queryStart = microtime(true);
        return true;
    }

    function onEndDBQuery($obj, $query, &$result)
    {
        $endTime = microtime(true);
        $this->queryTimes[] = round(($endTime - $this->queryStart) * 1000);
        $this->queries[] = trim(preg_replace('/\s/', ' ', $query));
        $this->queryStart = 0;

        return true;
    }

    function cleanup()
    {
        if (count($this->queryTimes) == 0) {
            $this->log(LOG_INFO, sprintf('0 queries this hit.'));
        } else {
            $this->log(LOG_INFO, sprintf('%d queries this hit (total = %d, avg = %d, max = %d, min = %d)',
                                         count($this->queryTimes),
                                         array_sum($this->queryTimes),
                                         array_sum($this->queryTimes)/count($this->queryTimes),
                                         max($this->queryTimes),
                                         min($this->queryTimes)));
        }

        $verbose = common_config('sqlstats', 'verbose');

        if ($verbose) {
            foreach ($this->queries as $query) {
                $this->log(LOG_INFO, $query);
            }
        }
    }
}

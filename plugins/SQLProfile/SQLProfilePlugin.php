<?php
/*
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
 * @package SQLProfilePlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class SQLProfilePlugin extends Plugin
{
    private $recursionGuard = false;

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'SQLProfile',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:SQLProfile',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Debug tool to watch for poorly indexed DB queries.'));

        return true;
    }

    function onStartDBQuery($obj, $query, &$result)
    {
        if (!$this->recursionGuard && preg_match('/\bselect\b/i', $query)) {
            $this->recursionGuard = true;
            $xobj = clone($obj);
            $explain = $xobj->query('EXPLAIN ' . $query);
            $this->recursionGuard = false;

            while ($xobj->fetch()) {
                $extra = $xobj->Extra;
                $evil = (strpos($extra, 'Using filesort') !== false) ||
                        (strpos($extra, 'Using temporary') !== false);
                if ($evil) {
                    $xquery = $xobj->sanitizeQuery($query);
                    common_log(LOG_DEBUG, "$extra | $xquery");
                }
            }
        }
        return true;
    }
}

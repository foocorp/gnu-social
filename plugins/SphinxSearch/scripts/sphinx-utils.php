<?php
/*
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
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

function sphinx_use_network()
{
    return have_option('network');
}

function sphinx_base()
{
    if (have_option('base')) {
        return get_option_value('base');
    } else {
        return "/usr/local/sphinx";
    }
}

function sphinx_iterate_sites($callback)
{
    if (sphinx_use_network()) {
        // @fixme this should use, like, some kind of config
        Status_network::setupDB('localhost', 'statusnet', 'statuspass', 'statusnet');
        $sn = new Status_network();
        if (!$sn->find()) {
            die("Confused... no sites in status_network table or lookup failed.\n");
        }
        while ($sn->fetch()) {
            $callback($sn);
        }
    } else {
        if (preg_match('!^(mysqli?|pgsql)://(.*?):(.*?)@(.*?)/(.*?)$!',
                common_config('db', 'database'), $matches)) {
            list(/*all*/, $dbtype, $dbuser, $dbpass, $dbhost, $dbname) = $matches;
            $sn = (object)array(
                'sitename' => common_config('site', 'name'),
                'dbhost' => $dbhost,
                'dbuser' => $dbuser,
                'dbpass' => $dbpass,
                'dbname' => $dbname);
            $callback($sn);
        } else {
            print "Unrecognized database configuration string in config.php\n";
            exit(1);
        }
    }
}

#!/usr/bin/env php
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = "t:l:v:k:";

$helptext = <<<ENDOFHELP
USAGE: showcache.php <args>
shows the cached object based on the args

  -t table     Table to look up
  -l column    Column to look up, default "id"
  -v value     Value to look up
  -k key       Key to look up; other args are ignored

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$karg = get_option_value('k');

if (!empty($karg)) {
    $k = Cache::key($karg);
} else {
    $table = get_option_value('t');
    if (empty($table)) {
        die("No table or key specified\n");
    }
    $column = get_option_value('l');
    if (empty($column)) {
        $column = 'id';
    }
    $value = get_option_value('v');

    $k = Memcached_DataObject::cacheKey($table, $column, $value);
}

print "Checking key '$k'...\n";

$c = Cache::instance();

if (empty($c)) {
    die("Can't initialize cache object!\n");
}

$obj = $c->get($k);

if (empty($obj)) {
    print "Empty.\n";
} else {
    var_dump($obj);
    print "\n";
}

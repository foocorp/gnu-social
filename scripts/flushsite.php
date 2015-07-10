#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

$shortoptions = 'd';
$longoptions = array('delete');

$helptext = <<<END_OF_FLUSHSITE_HELP
flushsite.php -s<sitename>
Flush the site with the given name from memcached.

END_OF_FLUSHSITE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$nickname = common_config('site', 'nickname');

$sn = Status_network::memGet('nickname', $nickname);

if (empty($sn)) {
    print "No such site.\n";
    exit(-1);
}

print "Flushing cache for {$nickname}...";
$sn->decache();
print "OK.\n";
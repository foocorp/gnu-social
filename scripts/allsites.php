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

// Abort if called from a web server

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<ENDOFHELP
allsites.php - list all sites configured for multi-site use

returns the nickname of each site configured for multi-site use

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$sn = new Status_network();

if ($sn->find()) {
    while ($sn->fetch()) {
        print "$sn->nickname\n";
    }
}
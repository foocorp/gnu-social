#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

/**
 * Utility script to get a list of daemons that should run, based on the
 * current configuration. This is used by startdaemons.sh to determine what
 * it should and shouldn't start up. The output is a list of space-separated
 * daemon names.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<ENDOFHELP
getvaliddaemons.php - print out a list of valid daemons that should be started
by the startdaemons script

ENDOFHELP;

// No unnecessary error reporting to avoid invalid daemon names
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

require_once INSTALLDIR.'/scripts/commandline.inc';

$daemons = array();

if (common_config('queue', 'daemon')) {
    $daemons[] = INSTALLDIR.'/scripts/queuedaemon.php';
}

if (Event::handle('GetValidDaemons', array(&$daemons))) {
    foreach ($daemons as $daemon) {
        print $daemon . ' ';
    }
    print "\n";
}

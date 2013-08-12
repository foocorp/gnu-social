#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Script to print out current version of the software
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'u';
$longoptions = array('universe');

$helptext = <<<END_OF_CLEANUPCHANNELS_HELP
cleanupchannels.php [options]
Garbage-collects old realtime channels

-u --universe Do all sites

END_OF_CLEANUPCHANNELS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function cleanupChannels()
{
    $rc = new Realtime_channel();

    $rc->selectAdd();
    $rc->selectAdd('channel_key');

    $rc->whereAdd('modified < "' . common_sql_date(time() - Realtime_channel::TIMEOUT) . '"');

    if ($rc->find()) {
        $keys = $rc->fetchAll();

        foreach ($keys as $key) {
            $rc = Realtime_channel::staticGet('channel_key', $key);
            if (!empty($rc)) {
                printfv("Deleting realtime channel '$key'\n");
                $rc->delete();
            }
        }
    }
}

if (have_option('u', 'universe')) {
    $sn = new Status_network();
    if ($sn->find()) {
        while ($sn->fetch()) {
            $server = $sn->getServerName();
            StatusNet::init($server);
            cleanupChannels();
        }
    }
} else {
    cleanupChannels();
}

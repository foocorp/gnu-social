#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

/* 
 *  Run this from INSTALLDIR/plugins/OStatus/scripts
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));


$shortoptions = 'd';
$longoptions = array('dry-run');

$helptext = <<<END_OF_HELP
rm_bad_feedsubs.php [options]
Deletes feedsub records that are in the inconsistent state of "subscribe"
and are older than one hour. If the hub hasn't answered back in an hour
the hub is probably either broken or doesn't exist.'

      Options:

    -d --dry-run look but don't mess with it


END_OF_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

$dry = false;

if (have_option('d') || have_option('dry-run')) {
    $dry = true;
}

echo "Looking for feed subscriptions with dirty no good huburis...\n";

$feedsub = new FeedSub();
$feedsub->sub_state = 'subscribe';
$feedsub->whereAdd('created < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
$feedsub->find();
$cnt = 0;

while ($feedsub->fetch()) {
    echo "----------------------------------------------------------------------------------------\n";
    echo  '           feed: '
        . $feedsub->uri . "\n"
        . '        hub uri: '
        . $feedsub->huburi ."\n"
        . ' subscribe date: '
        . date('r', strtotime($feedsub->created)) . "\n";

    if (!$dry) {
        $feedsub->delete();
        echo "                 (DELETED)\n";
    } else {
        echo "                 (WOULD BE DELETED)\n";
    }
    echo "----------------------------------------------------------------------------------------\n";
    $cnt++;
}
if ($cnt == 0) {
    echo "None found.\n";
} else {
    echo "Deleted $cnt bogus hub URIs.\n";
}
echo "Done.\n";

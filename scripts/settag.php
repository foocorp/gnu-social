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

$helptext = <<<END_OF_SETTAG_HELP
settag.php [options] <site> <tag>
Set the tag <tag> for site <site>.

With -d, delete the tag.

END_OF_SETTAG_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (count($args) < 1) {
    show_help();
    exit(1);
}

$nickname = $args[0];
$sn = Status_network::memGet('nickname', $nickname);

if (empty($sn)) {
    print "No such site ($nickname).\n";
    exit(-1);
}

$tags = $sn->getTags();

if (count($args) == 1) {
	print(implode(', ', $tags) . "\n");
	exit(0);
}
$tag = $args[1];
$i = array_search($tag, $tags);

if ($i !== false) {
    if (have_option('d', 'delete')) { // Delete
        unset($tags[$i]);

        $result = $sn->setTags($tags);
        if (!$result) {
            print "Couldn't update.\n";
            exit(-1);
        }
    } else {
        print "Already set.\n";
        exit(0);
    }
} else {
    if (have_option('d', 'delete')) { // Delete
        print "No such tag.\n";
        exit(0);
    } else {
        $tags[] = $tag;
        $result = $sn->setTags($tags);
        if (!$result) {
            print "Couldn't update.\n";
            exit(-1);
        }
    }
}

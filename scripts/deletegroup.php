#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, 2010, StatusNet, Inc.
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

$shortoptions = 'i::n::y';
$longoptions = array('id=', 'nickname=', 'yes');

$helptext = <<<END_OF_DELETEGROUP_HELP
deletegroup.php [options]
deletes a group from the database

  -i --id       ID of the group
  -n --nickname nickname of the group
  -y --yes      do not wait for confirmation

END_OF_DELETEGROUP_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $group = User_group::getKV('id', $id);
    if (empty($group)) {
        print "Can't find group with ID $id\n";
        exit(1);
    }
} else if (have_option('n', 'nickname')) {
    $nickname = get_option_value('n', 'nickname');
    $local = Local_group::getKV('nickname', $nickname);
    if (empty($local)) {
        print "Can't find group with nickname '$nickname'\n";
        exit(1);
    }
    $group = User_group::getKV('id', $local->group_id);
} else {
    print "You must provide either an ID or a nickname.\n";
    print "\n";
    print $helptext;
    exit(1);
}

if (!have_option('y', 'yes')) {
    print "About to PERMANENTLY delete group '{$group->nickname}' ({$group->id}). Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Deleting...";
$group->delete();
print "DONE.\n";

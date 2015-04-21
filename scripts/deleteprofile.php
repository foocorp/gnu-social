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

$shortoptions = 'i::n::u::y';
$longoptions = array('id=', 'nickname=', 'uri=', 'yes');

$helptext = <<<END_OF_DELETEUSER_HELP
deleteprofile.php [options]
deletes a profile from the database

  -i --id       ID of the profile
  -n --nickname nickname of a local user
  -u --uri      OStatus profile URI (only remote users, requires OStatus plugin)
  -y --yes      do not wait for confirmation

END_OF_DELETEUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $profile = Profile::getKV('id', $id);
    if (!$profile instanceof Profile) {
        print "Can't find profile with ID $id\n";
        exit(1);
    }
} else if (have_option('n', 'nickname')) {
    $nickname = get_option_value('n', 'nickname');
    $user = User::getKV('nickname', $nickname);
    if (!$user instanceof User) {
        print "Can't find user with nickname '$nickname'\n";
        exit(1);
    }
    $profile = $user->getProfile();
} else if (have_option('u', 'uri')) {
    $uri = get_option_value('u', 'uri');
    $oprofile = Ostatus_profile::getKV('uri', $uri);
    if (!$oprofile instanceof Ostatus_profile) {
        print "Can't find profile with URI '$uri'\n";
        exit(1);
    }
    $profile = $oprofile->localProfile();
} else {
    print "You must provide either an ID, a URI or a nickname.\n";
    exit(1);
}

if (!have_option('y', 'yes')) {
    print "About to PERMANENTLY delete profile '".$profile->getNickname()."' ({$profile->id}). Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Deleting...";
$profile->delete();
print "DONE.\n";

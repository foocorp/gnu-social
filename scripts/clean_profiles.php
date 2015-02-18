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

$shortoptions = 'y';
$longoptions = array('yes');

$helptext = <<<END_OF_HELP
clean_profiles.php [options]
Deletes all profile table entries where the profile does not occur in the
notice table, is not a group and is not a local user. Very MySQL specific I think.

WARNING: This has not been tested thoroughly. Maybe we've missed a table to compare somewhere.

  -y --yes      do not wait for confirmation

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (!have_option('y', 'yes')) {
    print "About to delete profiles that we think are useless to save. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Deleting";
$profile = new Profile();
$profile->query('SELECT * FROM profile WHERE ' .
                'NOT (SELECT COUNT(*) FROM notice WHERE profile_id=profile.id) ' .
                'AND NOT (SELECT COUNT(*) FROM user WHERE user.id=profile.id) ' .
                'AND NOT (SELECT COUNT(*) FROM user_group WHERE user_group.profile_id=profile.id) ' .
                'AND NOT (SELECT COUNT(*) FROM subscription WHERE subscriber=profile.id OR subscribed=profile.id) ');
while ($profile->fetch()) {
    echo ' '.$profile->getID().':'.$profile->getNickname();
    $profile->delete();
}
print "\nDONE.\n";

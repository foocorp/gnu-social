#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
$longoptions = array('id=', 'nickname=', 'yes', 'dry-run', 'all');

$helptext = <<<END_OF_HELP
strip_geo.php [options]
Removes geolocation info from the given user's notices.

  -i --id       ID of the user (may be a remote profile)
  -n --nickname nickname of the user
  -y --yes      do not wait for confirmation
     --dry-run  list affected notices without deleting
     --all      run over and decache all messages, even if they don't
                have geo data now (helps to fix cache bugs)

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $profile = Profile::getKV('id', $id);
    if (empty($profile)) {
        print "Can't find local or remote profile with ID $id\n";
        exit(1);
    }
} else if (have_option('n', 'nickname')) {
    $nickname = get_option_value('n', 'nickname');
    $user = User::getKV('nickname', $nickname);
    if (empty($user)) {
        print "Can't find local user with nickname '$nickname'\n";
        exit(1);
    }
    $profile = $user->getProfile();
} else {
    print "You must provide either an ID or a nickname.\n\n";
    show_help();
    exit(1);
}

if (!have_option('y', 'yes') && !have_option('--dry-run')) {
    print "About to PERMANENTLY remove geolocation data from user '{$profile->nickname}' ({$profile->id})'s notices. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

// @fixme for a very prolific poster this could be too many.
$notice = new Notice();
$notice->profile_id = $profile->id;
if (have_option('--all')) {
    print "Finding all notices by $profile->nickname...";
} else {
    print "Finding notices by $profile->nickname with geolocation data...";
    $notice->whereAdd("lat != ''");
}
$notice->find();

if ($notice->N) {
    print " $notice->N found.\n";
    while ($notice->fetch()) {
        print "notice id $notice->id ";
        if (have_option('v') || have_option('--verbose')) {
            print "({$notice->lat},{$notice->lon}) ";
            if ($notice->location_ns) {
                print "ns {$notice->location_ns} id {$notice->location_id} ";
            }
        }
        if (have_option('--dry-run')) {
            // sucka
            echo "(skipped)";
        } else {
            // note: setting fields to null and calling update() doesn't save the nulled fields
            $orig = clone($notice);
            $update = clone($notice);

            // In theory we could hit a chunk of notices at once in the UPDATE,
            // but we're going to have to decache them individually anyway and
            // it doesn't hurt to make sure we don't hold up replication with
            // what might be a very slow single UPDATE.
            $query = sprintf('UPDATE notice ' .
                             'SET lat=NULL,lon=NULL,location_ns=NULL,location_id=NULL ' .
                             'WHERE id=%d', $notice->id);
            $ok = $update->query($query);
            if ($ok) {
                // And now we decache him manually, as query() doesn't know what we're doing...
                $orig->decache();
                echo "(removed)";
            } else {
                echo "(unchanged?)";
            }
        }
        print "\n";
    }
} else {
    print " none found.\n";
}

print "DONE.\n";

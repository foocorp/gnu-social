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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$longoptions = array('dry-run', 'start=', 'end=');

$helptext = <<<END_OF_HELP
fixup_blocks.php [options]
Finds profile blocks where the unsubscription didn't complete,
and removes the offending subscriptions.

     --dry-run  look but don't touch

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

/**
 * Fetch subscriptions that should be disallowed by a block
 */
function get_blocked_subs()
{
    $query =     "SELECT subscription.* " .
                   "FROM subscription " .
             "INNER JOIN profile_block " .
                     "ON blocker=subscribed " .
                    "AND blocked=subscriber";
    $subscription = new Subscription();
    $subscription->query($query);
    return $subscription;
}


$dry = have_option('dry-run');
$sub = get_blocked_subs();
$count = $sub->N;
while ($sub->fetch()) {
    $subber = Profile::getKV('id', $sub->subscriber);
    $subbed = Profile::getKV('id', $sub->subscribed);
    if (!$subber || !$subbed) {
        print "Bogus entry! $sub->subscriber subbed to $sub->subscribed\n";
        continue;
    }
    print "$subber->nickname ($subber->id) blocked but subbed to $subbed->nickname ($subbed->id)";
    if ($dry) {
        print ": skipping; dry run\n";
    } else {
        Subscription::cancel($subber, $subbed);
        print ": removed\n";
    }
}
print "\n";

if ($dry && $count > 0) {
    print "Be sure to run without --dry-run to remove the bad entries!\n";
} else {
    print "done.\n";
}

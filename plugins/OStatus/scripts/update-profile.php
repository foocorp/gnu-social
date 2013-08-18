#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$helptext = <<<END_OF_HELP
update-profile.php [options] http://example.com/profile/url

Rerun profile and feed info discovery for the given OStatus remote profile,
and reinitialize its PuSH subscription for the given feed. This may help get
things restarted if the hub or feed URLs have changed for the profile.


END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (empty($args[0]) || !Validate::uri($args[0])) {
    print "$helptext";
    exit(1);
}

$uri = $args[0];


$oprofile = Ostatus_profile::getKV('uri', $uri);

if (!$oprofile) {
    print "No OStatus remote profile known for URI $uri\n";
    exit(1);
}

print "Old profile state for $oprofile->uri\n";
showProfile($oprofile);

print "\n";
print "Re-running feed discovery for profile URL $oprofile->uri\n";
// @fixme will bork where the URI isn't the profile URL for now
$discover = new FeedDiscovery();
$feedurl = $discover->discoverFromURL($oprofile->uri);
$huburi = $discover->getHubLink();
$salmonuri = $discover->getAtomLink(Salmon::NS_REPLIES);

print "  Feed URL: $feedurl\n";
print "  Hub URL: $huburi\n";
print "  Salmon URL: $salmonuri\n";

if ($feedurl != $oprofile->feeduri || $salmonuri != $oprofile->salmonuri) {
    print "\n";
    print "Updating...\n";
    // @fixme update keys :P
    #$orig = clone($oprofile);
    #$oprofile->feeduri = $feedurl;
    #$oprofile->salmonuri = $salmonuri;
    #$ok = $oprofile->update($orig);
    $ok = $oprofile->query('UPDATE ostatus_profile SET ' .
        'feeduri=\'' . $oprofile->escape($feedurl) . '\',' .
        'salmonuri=\'' . $oprofile->escape($salmonuri) . '\' ' .
        'WHERE uri=\'' . $oprofile->escape($uri) . '\'');

    if (!$ok) {
        print "Failed to update profile record...\n";
        exit(1);
    }

    $oprofile->decache();
} else {
    print "\n";
    print "Ok, ostatus_profile record unchanged.\n\n";
}

$sub = FeedSub::ensureFeed($feedurl);

if ($huburi != $sub->huburi) {
    print "\n";
    print "Updating hub record for feed; was $sub->huburi\n";
    $orig = clone($sub);
    $sub->huburi = $huburi;
    $ok = $sub->update($orig);

    if (!$ok) {
        print "Failed to update sub record...\n";
        exit(1);
    }
} else {
    print "\n";
    print "Feed record ok, not changing.\n\n";
}

print "\n";
print "Pinging hub $sub->huburi with new subscription for $sub->uri\n";
$ok = $sub->subscribe();

if ($ok) {
    print "ok\n";
} else {
    print "Could not confirm.\n";
}

$o2 = Ostatus_profile::getKV('uri', $uri);

print "\n";
print "New profile state:\n";
showProfile($o2);

print "\n";
print "New feed state:\n";
$sub2 = FeedSub::ensureFeed($feedurl);
showSub($sub2);

function showProfile($oprofile)
{
    print "  Feed URL: $oprofile->feeduri\n";
    print "  Salmon URL: $oprofile->salmonuri\n";
    print "  Avatar URL: $oprofile->avatar\n";
    print "  Profile ID: $oprofile->profile_id\n";
    print "  Group ID: $oprofile->group_id\n";
    print "  Record created: $oprofile->created\n";
    print "  Record modified: $oprofile->modified\n";
}

function showSub($sub)
{
    print "  Subscription state: $sub->sub_state\n";
    print "  Verify token: $sub->verify_token\n";
    print "  Signature secret: $sub->secret\n";
    print "  Sub start date: $sub->sub_start\n";
    print "  Record created: $sub->created\n";
    print "  Record modified: $sub->modified\n";
}

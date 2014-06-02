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

$longoptions = array('verify', 'slap=', 'notice=');

$helptext = <<<END_OF_HELP
slap.php [options]

Test generation and sending of magic envelopes for Salmon slaps.

     --notice=N   generate entry for this notice number
     --verify     send signed magic envelope to Tuomas Koski's test service
     --slap=<url> send signed Salmon slap to the destination endpoint


END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (!have_option('--notice')) {
    print "$helptext";
    exit(1);
}

$notice_id = get_option_value('--notice');

$notice = Notice::getKV('id', $notice_id);
$profile = $notice->getProfile();
$entry = $notice->asAtomEntry(true);

echo "== Original entry ==\n\n";
print $entry;
print "\n\n";

$magic_env = MagicEnvelope::signAsUser($entry, $profile->getUser());
$envxml = $magic_env->toXML();

echo "== Signed envelope ==\n\n";
print $envxml;
print "\n\n";

echo "== Testing local verification ==\n\n";
$magic_env = new MagicEnvelope($envxml);
$activity = new Activity($magic_env->getPayload()->documentElement);
$actprofile = Profile::fromUri($activity->actor->id);
$ok = $magic_env->verify($actprofile);
if ($ok) {
    print "OK\n\n";
} else {
    print "FAIL\n\n";
}

if (have_option('--verify')) {
    $url = 'http://www.madebymonsieur.com/ostatus_discovery/magic_env/validate/';
    echo "== Testing remote verification ==\n\n";
    print "Sending for verification to $url ...\n";

    $client = new HTTPClient();
    $response = $client->post($url, array(), array('magic_env' => $envxml));

    print $response->getStatus() . "\n\n";
    print $response->getBody() . "\n\n";
}

if (have_option('--slap')) {
    $url = get_option_value('--slap');
    echo "== Remote salmon slap ==\n\n";
    print "Sending signed Salmon slap to $url ...\n";

    $ok = Salmon::post($url, $entry, $profile->getUser());
    if ($ok) {
        print "OK\n\n";
    } else {
        print "FAIL\n\n";
    }
}

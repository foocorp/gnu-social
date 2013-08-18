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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$longoptions = array('dry-run');

$helptext = <<<END_OF_USERROLE_HELP
fixup_shadow.php [options]
Patches up stray ostatus_profile entries with corrupted shadow entries
for local users and groups.

     --dry-run  look but don't touch

END_OF_USERROLE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$dry = have_option('dry-run');

// Look for user.uri matches... These may not match up with the current
// URL schema if the site has changed names.
echo "Checking for bogus ostatus_profile entries matching user.uri...\n";

$user = new User();
$oprofile = new Ostatus_profile();
$user->joinAdd($oprofile, 'INNER', 'oprofile', 'uri');
$user->find();
$count = $user->N;
echo "Found $count...\n";

while ($user->fetch()) {
    $uri = $user->uri;
    echo "user $user->id ($user->nickname) hidden by $uri";
    if ($dry) {
        echo " - skipping\n";
    } else {
        echo " - removing bogus ostatus_profile entry...";
        $evil = Ostatus_profile::getKV('uri', $uri);
        $evil->delete();
        echo "  ok\n";
    }
}
echo "\n";

// Also try user_group.uri matches for local groups.
// Not all group entries will have this filled out, though, as it's new!
echo "Checking for bogus ostatus_profile entries matching local user_group.uri...\n";
$group = new User_group();
$group->joinAdd(array('uri', 'ostatus_profile:uri'));
$group->joinAdd(array('id', 'local_group:group_id'));
$group->find();
$count = $group->N;
echo "Found $count...\n";

while ($group->fetch()) {
    $uri = $group->uri;
    echo "group $group->id ($group->nickname) hidden by $uri";
    if ($dry) {
        echo " - skipping\n";
    } else {
        echo " - removing bogus ostatus_profile entry...";
        $evil = Ostatus_profile::getKV('uri', $uri);
        $evil->delete();
        echo "  ok\n";
    }
}
echo "\n";

// And there may be user_group entries remaining where we've already killed
// the ostatus_profile. These were "harmless" until our lookup started actually
// using the uri field, at which point we can clearly see it breaks stuff.
echo "Checking for leftover bogus user_group.uri entries obscuring local_group entries...\n";

$group = new User_group();
$group->joinAdd(array('id', 'local_group:group_id'), 'LEFT');
$group->whereAdd('group_id IS NULL');


$marker = mt_rand(31337, 31337000);
$groupTemplate = common_local_url('groupbyid', array('id' => $marker));
$encGroup = $group->escape($groupTemplate, true);
$encGroup = str_replace($marker, '%', $encGroup);
echo "  LIKE '$encGroup'\n";
$group->whereAdd("uri LIKE '$encGroup'");

$group->find();
$count = $group->N;
echo "Found $count...\n";

while ($group->fetch()) {
    $uri = $group->uri;
    if (preg_match('!/group/(\d+)/id!', $uri, $matches)) {
        $id = intval($matches[1]);
        $local = Local_group::getKV('group_id', $id);
        if ($local) {
            $nick = $local->nickname;
        } else {
            $nick = '<deleted>';
        }
        echo "local group $id ($local->nickname) hidden by $uri (bogus group id $group->id)";
        if ($dry) {
            echo " - skipping\n";
        } else {
            echo " - removing bogus user_group entry...";
            $evil = User_group::getKV('id', $group->id);
            $evil->delete();
            echo "  ok\n";
        }
    }
}
echo "\n";


// Fallback?
echo "Checking for bogus profiles blocking local users/groups by URI pattern match...\n";
$oprofile = new Ostatus_profile();

$marker = mt_rand(31337, 31337000);

$profileTemplate = common_local_url('userbyid', array('id' => $marker));
$encProfile = $oprofile->escape($profileTemplate, true);
$encProfile = str_replace($marker, '%', $encProfile);
echo "  LIKE '$encProfile'\n";

$groupTemplate = common_local_url('groupbyid', array('id' => $marker));
$encGroup = $oprofile->escape($groupTemplate, true);
$encGroup = str_replace($marker, '%', $encGroup);
echo "  LIKE '$encGroup'\n";

$sql = "SELECT * FROM ostatus_profile WHERE uri LIKE '%s' OR uri LIKE '%s'";
$oprofile->query(sprintf($sql, $encProfile, $encGroup));

$count = $oprofile->N;
echo "Found $count...\n";

while ($oprofile->fetch()) {
    $uri = $oprofile->uri;
    if (preg_match('!/group/(\d+)/id!', $oprofile->uri, $matches)) {
        $id = intval($matches[1]);
        $group = Local_group::getKV('group_id', $id);
        if ($group) {
            $nick = $group->nickname;
        } else {
            $nick = '<deleted>';
        }
        echo "group $id ($nick) hidden by $uri";
    } else if (preg_match('!/user/(\d+)!', $uri, $matches)) {
        $id = intval($matches[1]);
        $user = User::getKV('id', $id);
        if ($user) {
            $nick = $user->nickname;
        } else {
            $nick = '<deleted>';
        }
        echo "user $id ($nick) hidden by $uri";
    } else {
        echo "$uri matched query, but we don't recognize it.\n";
        continue;
    }

    if ($dry) {
        echo " - skipping\n";
    } else {
        echo " - removing bogus ostatus_profile entry...";
        $evil = clone($oprofile);
        $evil->delete();
        echo "  ok\n";
    }
}

if ($count && $dry) {
    echo "NO CHANGES MADE -- To delete the bogus entries, run again without --dry-run option.\n";
} else {
    echo "done.\n";
}

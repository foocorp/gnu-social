#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, 2010 StatusNet, Inc.
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

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all', 'dry-run');

$helptext = <<<END_OF_UPDATEAVATARURL_HELP
fixup_group_uri.php [options]
Fill in unstored URIs for groups in the system (added during 0.9)

  -i --id       ID of group to update
  -n --nickname nickname of the group to update
  -a --all      update all
     --dry-run  don't change anything

END_OF_UPDATEAVATARURL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $group = User_group::staticGet('id', $id);
        if (empty($group)) {
            throw new Exception("Can't find group with id '$id'.");
        }
        updateGroupUri($group);
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $group = User_group::staticGet('nickname', $nickname);
        if (empty($group)) {
            throw new Exception("Can't find group with nickname '$nickname'");
        }
        updateGroupUri($group);
    } else if (have_option('a', 'all')) {
        $group = new User_group();
        $group->whereAdd('uri IS NULL');
        if ($group->find()) {
            while ($group->fetch()) {
                updateGroupUri($group);
            }
        }
    } else {
        show_help();
        exit(1);
    }
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

function updateGroupUri($group)
{
    if (!have_option('q', 'quiet')) {
        print "Updating URI for group '".$group->nickname."' (".$group->id.")...";
    }

    if (empty($group->uri)) {
        // Using clone here was screwing up the group->find() iteration
        $orig = User_group::staticGet('id', $group->id);

        $group->uri = $group->getUri();
        if (have_option('dry_run')) {
            echo " would have set $group->uri ";
        } else {
            if (!$group->update($orig)) {
                throw new Exception("Can't update uri for group " . $group->nickname . ".");
            }
            echo " set $group->uri ";
        }
    } else {
        print " already set, keeping $group->uri ";
    }

    if (have_option('v', 'verbose')) {
        print "DONE.";
    }
    if (!have_option('q', 'quiet') || have_option('v', 'verbose')) {
        print "\n";
    }
}

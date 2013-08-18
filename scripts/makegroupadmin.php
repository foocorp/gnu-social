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

$shortoptions = 'g:n:';
$longoptions = array('nickname=', 'group=');

$helptext = <<<END_OF_MAKEGROUPADMIN_HELP
makegroupadmin.php [options]
makes a user the admin of a group

  -g --group    group to add an admin to
  -n --nickname nickname of the new admin

END_OF_MAKEGROUPADMIN_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$nickname = get_option_value('n', 'nickname');
$groupname = get_option_value('g', 'group');

if (empty($nickname) || empty($groupname)) {
    print "Must provide a nickname and group.\n";
    exit(1);
}

try {

    $user = User::getKV('nickname', $nickname);

    if (empty($user)) {
        throw new Exception("No user named '$nickname'.");
    }

    $group = User_group::getKV('nickname', $groupname);

    if (empty($group)) {
        throw new Exception("No group named '$groupname'.");
    }

    $member = Group_member::pkeyGet(array('group_id' => $group->id,
                                          'profile_id' => $user->id));

    if (empty($member)) {
        $member = new Group_member();

        $member->group_id   = $group->id;
        $member->profile_id = $user->id;
        $member->created    = common_sql_now();

        if (!$member->insert()) {
            throw new Exception("Can't add '$nickname' to '$groupname'.");
        }
    }

    if ($member->is_admin) {
        throw new Exception("'$nickname' is already an admin of '$groupname'.");
    }

    $orig = clone($member);

    $member->is_admin = 1;

    if (!$member->update($orig)) {
        throw new Exception("Can't make '$nickname' admin of '$groupname'.");
    }

} catch (Exception $e) {
    print $e->getMessage() . "\n";
    exit(1);
}

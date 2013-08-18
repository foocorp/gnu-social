<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012 StatusNet, Inc.
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

$shortoptions = 'i:n:g:G:';
$longoptions = array('id=', 'nickname=', 'group=', 'group-id=');

$helptext = <<<END_OF_HELP
leavegroup.php [options]

Removes a local user from a local group.

  -i --id       ID of user to remove
  -n --nickname nickname of the user to remove
  -g --group    nickname or alias of group
  -G --group-id ID of group

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = getUser();
    $lgroup = null;
    if (have_option('G', 'group-id')) {
        $gid = get_option_value('G', 'group-id');
        $lgroup = Local_group::getKV('group_id', $gid);
    } else if (have_option('g', 'group')) {
        $gnick = get_option_value('g', 'group');
        $lgroup = Local_group::getKV('nickname', $gnick);
    }
    if (empty($lgroup)) {
        throw new Exception("No such local group: $gnick");
    }
    $group = User_group::getKV('id', $lgroup->group_id);
    $user->leaveGroup($group);
    print "OK\n";
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

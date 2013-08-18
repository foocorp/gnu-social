#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

$helptext = <<<END_OF_PASSWORD_HELP
setpassword.php <username> <password>

Sets the password of user with name <username> to <password>

END_OF_PASSWORD_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (count($args) < 2) {
    show_help();
}

$nickname = $args[0];
$password = $args[1];

if (mb_strlen($password) < 6) {
    print "Password must be 6 characters or more.\n";
    exit(1);
}

$user = User::getKV('nickname', $nickname);

if (!$user) {
    print "No such user '$nickname'.\n";
    exit(1);
}

$original = clone($user);

$user->password = common_munge_password($password, $user->id);

if (!$user->update($original)) {
    print "Error updating user '$nickname'.\n";
    exit(1);
} else {
    print "Password for user '$nickname' updated.\n";
    exit(0);
}

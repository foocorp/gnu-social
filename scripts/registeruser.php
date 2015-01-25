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

$shortoptions = 'n:w:f:e:';
$longoptions = array('nickname=', 'password=', 'fullname=', 'email=');

$helptext = <<<END_OF_REGISTERUSER_HELP
registeruser.php [options]
registers a user in the database

  -n --nickname nickname of the new user
  -w --password password of the new user
  -f --fullname full name of the new user (optional)
  -e --email    email address of the new user (optional)

END_OF_REGISTERUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$nickname = get_option_value('n', 'nickname');
$password = get_option_value('w', 'password');
$fullname = get_option_value('f', 'fullname');

$email = get_option_value('e', 'email');

if (empty($nickname) || empty($password)) {
    print "Must provide a username and password.\n";
    exit(1);
}

try {

    $user = User::getKV('nickname', $nickname);

    if (!empty($user)) {
        throw new Exception("A user named '$nickname' already exists.");
    }

    $user = User::register(array('nickname' => $nickname,
                                 'password' => $password,
                                 'fullname' => $fullname));

    if (empty($user)) {
        throw new Exception("Can't register user '$nickname' with password '$password' and fullname '$fullname'.");
    }

    if (!empty($email)) {

        $orig = clone($user);

        $user->email = $email;

        if (!$user->updateWithKeys($orig)) {
            print "Failed!\n";
            throw new Exception("Can't update email address.");
        }
    }

} catch (Exception $e) {
    print $e->getMessage() . "\n";
    exit(1);
}

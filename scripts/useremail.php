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

$shortoptions = 'i:n:e:';
$longoptions = array('id=', 'nickname=', 'email=');

$helptext = <<<END_OF_USEREMAIL_HELP
useremail.php [options]
Queries a user's registered email address, or queries the users with a given registered email.

  -i --id       id of the user to query
  -n --nickname nickname of the user to query
  -e --email    email address to query

END_OF_USEREMAIL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $user = User::getKV('id', $id);
    if (empty($user)) {
        print "Can't find user with ID $id\n";
        exit(1);
    }
} else if (have_option('n', 'nickname')) {
    $nickname = get_option_value('n', 'nickname');
    $user = User::getKV('nickname', $nickname);
    if (empty($user)) {
        print "Can't find user with nickname '$nickname'\n";
        exit(1);
    }
}

if (!empty($user)) {
    if (empty($user->email)) {
        // Check for unconfirmed emails
        $unconfirmed_email = new Confirm_address();
        $unconfirmed_email->user_id = $user->id;
        $unconfirmed_email->address_type = 'email';
        $unconfirmed_email->find(true);

        if (empty($unconfirmed_email->address)) {
            print "No email registered for user '$user->nickname'\n";
        } else {
            print "Unconfirmed Adress: $unconfirmed_email->address\n";
        }
    } else {
        print "$user->email\n";
    }
    exit(0);
}

if (have_option('e', 'email')) {
    $user = new User();
    $user->email = get_option_value('e', 'email');
    $user->find(false);
    if (!$user->fetch()) {
        // Check unconfirmed emails
        $unconfirmed_email = new Confirm_address();
        $unconfirmed_email->address = $user->email;
        $unconfirmed_email->address_type = 'email';
        $unconfirmed_email->find(true);

        if (empty($unconfirmed_email->user_id)) {
            print "No users with email $user->email\n";
        } else {
            $user=User::getKV('id', $unconfirmed_email->user_id);
            print "Unconfirmed Address: $user->id $user->nickname\n";
        }
        exit(0);
    }
    do {
        print "$user->id $user->nickname\n";
    } while ($user->fetch());
} else {
    print "You must provide either an ID, email, or a nickname.\n";
    exit(1);
}

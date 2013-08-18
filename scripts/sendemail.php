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

$shortoptions = 'i:n:';
$longoptions = array('id=', 'nickname=', 'subject=');

$helptext = <<<END_OF_USEREMAIL_HELP
sendemail.php [options] < <message body>
Sends given email text to user.

  -i --id       id of the user to query
  -n --nickname nickname of the user to query
     --subject  mail subject line (required)

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
} else {
    print "You must provide a user by --id or --nickname\n";
    exit(1);
}

if (empty($user->email)) {
    // @fixme unconfirmed address?
    print "No email registered for user '$user->nickname'\n";
    exit(1);
}

if (!have_option('subject')) {
    echo "You must provide a subject line for the mail in --subject='...' param.\n";
    exit(1);
}
$subject = get_option_value('subject');

if (posix_isatty(STDIN)) {
    print "You must provide message input on stdin!\n";
    exit(1);
}
$body = file_get_contents('php://stdin');

print "Sending to $user->email...";
if (mail_to_user($user, $subject, $body)) {
    print " done\n";
} else {
    print " failed.\n";
    exit(1);
}


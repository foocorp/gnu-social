#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = "e:";

$helptext = <<<END_OF_REGISTERBYEMAIL_HELP
USAGE: registerbyemail.php
Registers a new user by email address and sends a confirmation email

  -e email     Email to register

END_OF_REGISTERBYEMAIL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$email = get_option_value('e', 'email');

$parts = explode('@', $email);
$nickname = common_nicknamize($parts[0]);

$user = User::getKV('nickname', $nickname);

if (!empty($user)) {
    $confirm = new Confirm_address();

    $confirm->user_id      = $user->id;
    $confirm->address_type = 'email';

    if ($confirm->find(true)) {
        $url = common_local_url('confirmfirstemail',
                                array('code' => $confirm->code));

        print "$url\n";
    } else {
        print "User not waiting for confirmation.\n";
    }

    exit;
}

$user = User::register(array('nickname' => $nickname,
                             'password' => null));

$confirm = new Confirm_address();
$confirm->code = common_confirmation_code(128);
$confirm->user_id = $user->id;
$confirm->address = $email;
$confirm->address_type = 'email';

$confirm->insert();

$url = common_local_url('confirmfirstemail',
                        array('code' => $confirm->code));

print "$url\n";

mail_confirm_address($user,
                     $confirm->code,
                     $user->nickname,
                     $email,
                     $url);

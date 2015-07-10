#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Script to print out current version of the software
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

$shortoptions = 'wt::';
$longoptions = array('welcome', 'template=');

$helptext = <<<END_OF_INSTALLFOREMAIL_HELP

installforemail.php [options] <email address>
Create a new account and, if necessary, a new network for the given email address

-w --welcome   Send a welcome email
-t --template= Use this email template

END_OF_INSTALLFOREMAIL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$email = $args[0];

$sendWelcome = have_option('w', 'welcome');

if ($sendWelcome && have_option('t', 'template')) {
    $template = get_option_value('t', 'template');
}

try {

    $confirm = DomainStatusNetworkPlugin::registerEmail($email);

    if ($sendWelcome) {
        EmailRegistrationPlugin::sendConfirmEmail($confirm, $template);
    }

    $confirmUrl = common_local_url('register', array('code' => $confirm->code));

    print $confirmUrl."\n";

} catch (Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
}

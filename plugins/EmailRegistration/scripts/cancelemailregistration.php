#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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

$shortoptions = 'd';
$longoptions = array('dryrun');

$helptext = <<<END_OF_REGISTEREMAILUSER_HELP
cancelemailregistration.php [options] <email address>

Options:
-d --dryrun   Do not actually delete the email registration and confirmation code

Cancel an email registration code

END_OF_REGISTEREMAILUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (count($args) == 0) {
    show_help();
}

$email = $args[0];

try {
    $confirm = Confirm_address::getByAddress($email, EmailRegistrationPlugin::CONFIRMTYPE);

    if (have_option('d', 'dryrun')) {
        print "[Dry run mode] Deleted confirmation code {$confirm->code} for {$confirm->address}.\n";
    } else {
        $confirm->delete();
        print "Deleted confirmation code {$confirm->code} for {$confirm->address}.\n";
    }
} catch (NoResultException $e) {
    print "Exception thrown for {$email}: {$e->getMessage()}";
}

<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_RESTOREUSER_HELP
restoreuser.php [options]
Restore a backed-up user file to the database. If
neither ID or name provided, will create a new user.

  -i --id       ID of user to export
  -n --nickname nickname of the user to export
  -f --file     file to read from (STDIN by default)

END_OF_RESTOREUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';


function getActivityStreamDocument()
{
    $filename = get_option_value('f', 'file');

    if (empty($filename)) {
        show_help();
        exit(1);
    }

    if (!file_exists($filename)) {
        throw new Exception("No such file '$filename'.");
    }

    if (!is_file($filename)) {
        throw new Exception("Not a regular file: '$filename'.");
    }

    if (!is_readable($filename)) {
        throw new Exception("File '$filename' not readable.");
    }

    // TRANS: Commandline script output. %s is the filename that contains a backup for a user.
    printfv(_("Getting backup from file '%s'.")."\n",$filename);


    $xml = file_get_contents($filename);

    return $xml;
}


try {
    try {
        $user = getUser();
    } catch (NoUserArgumentException $noae) {
        $user = null;
    }
    $xml = getActivityStreamDocument();
    $qm = QueueManager::get();
    $qm->enqueue(array($user, $xml, true), 'feedimp');
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

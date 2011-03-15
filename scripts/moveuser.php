<?php
/**
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

$shortoptions = 'i:n:r:w:y';
$longoptions = array('id=', 'nickname=', 'remote=', 'password=');

$helptext = <<<END_OF_MOVEUSER_HELP
moveuser.php [options]
Move a local user to a remote account.

  -i --id       ID of user to move
  -n --nickname nickname of the user to move
  -r --remote   Full ID of remote users
  -w --password Password of remote user
  -y --yes      do not wait for confirmation

Remote user identity must be a Webfinger (nickname@example.com) or 
an HTTP or HTTPS URL (http://example.com/social/site/user/nickname).

END_OF_MOVEUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {

    $user = getUser();

    $remote = get_option_value('r', 'remote');

    if (empty($remote)) {
        show_help();
        exit(1);
    }

    $password = get_option_value('w', 'password');

    if (!have_option('y', 'yes')) {
        print "WARNING: EXPERIMENTAL FEATURE! Moving accounts will delete data from the source site.\n";
        print "\n";
        print "About to PERMANENTLY move user '{$user->nickname}' to $remote. Are you sure? [y/N] ";
        $response = fgets(STDIN);
        if (strtolower(trim($response)) != 'y') {
            print "Aborting.\n";
            exit(0);
        }
    }

    $qm = QueueManager::get();

    $qm->enqueue(array($user, $remote, $password), 'acctmove');

} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

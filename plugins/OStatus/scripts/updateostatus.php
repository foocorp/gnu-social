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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all');

$helptext = <<<END_OF_UPDATEOSTATUS_HELP
updateostatus.php [options]
update the OMB subscriptions of a user to use OStatus if possible

  -i --id       ID of user to update
  -n --nickname nickname of the user to update
  -a --all      update all

END_OF_UPDATEOSTATUS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::getKV('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
        updateOStatus($user);
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::getKV('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'.");
        }
        updateOStatus($user);
    } else if (have_option('a', 'all')) {
        $user = new User();
        if ($user->find()) {
            while ($user->fetch()) {
                try {
                    updateOStatus($user);
                } catch (Exception $e) {
                    common_log(LOG_NOTICE, "Couldn't convert OMB subscriptions ".
                               "for {$user->nickname} to OStatus: " . $e->getMessage());
                }
            }
        }
    } else {
        show_help();
        exit(1);
    }
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

function updateOStatus($user)
{
    if (!have_option('q', 'quiet')) {
        echo "{$user->nickname}...";
    }

    $up = $user->getProfile();

    $sp = $up->getSubscribed();

    $rps = array();

    while ($sp->fetch()) {
        $remote = Remote_profile::getKV('id', $sp->id);

        if (!empty($remote)) {
            $rps[] = clone($sp);
        }
    }

    if (!have_option('q', 'quiet')) {
        echo count($rps) . "\n";
    }

    foreach ($rps as $rp) {
        try {
            if (!have_option('q', 'quiet')) {
                echo "Checking {$rp->nickname}...";
            }

            $op = Ostatus_profile::ensureProfileURL($rp->profileurl);

            if (empty($op)) {
                echo "can't convert.\n";
                continue;
            } else {
                if (!have_option('q', 'quiet')) {
                    echo "Converting...";
                }
                Subscription::start($up, $op->localProfile());
                Subscription::cancel($up, $rp);
                if (!have_option('q', 'quiet')) {
                    echo "done.\n";
                }
            }

        } catch (Exception $e) {
            if (!have_option('q', 'quiet')) {
                echo "fail.\n";
            }
            common_log(LOG_NOTICE, "Couldn't convert OMB subscription (" . $up->nickname . ", " . $rp->nickname .
                       ") to OStatus: " . $e->getMessage());
            continue;
        }
    }
}

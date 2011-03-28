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

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);
define('LACONICA', true); // compatibility

require_once(INSTALLDIR . '/lib/common.php');

$start_at = ($argc > 1) ? $argv[1] : null;

common_log(LOG_INFO, 'Updating user inboxes.');

$user = new User();

if ($start_at) {
    $user->whereAdd('id >= ' . $start_at);
}

$cnt = $user->find();
$cache = Cache::instance();

while ($user->fetch()) {
    common_log(LOG_INFO, 'Updating inbox for user ' . $user->id);
    $user->query('BEGIN');
    $inbox = new Notice_inbox();
    $result = $inbox->query('INSERT LOW_PRIORITY INTO notice_inbox (user_id, notice_id, created) ' .
                            'SELECT ' . $user->id . ', notice.id, notice.created ' .
                            'FROM subscription JOIN notice ON subscription.subscribed = notice.profile_id ' .
                            'WHERE subscription.subscriber = ' . $user->id . ' ' .
                            'AND notice.created >= subscription.created ' . 
                            'AND NOT EXISTS (SELECT user_id, notice_id ' .
                            'FROM notice_inbox ' .
                            'WHERE user_id = ' . $user->id . ' ' . 
                            'AND notice_id = notice.id)');
    if (is_null($result) || $result === false) {
        common_log_db_error($inbox, 'INSERT', __FILE__);
        continue;
    }
    $orig = clone($user);
    $user->inboxed = 1;
    $result = $user->update($orig);
    if (!$result) {
        common_log_db_error($user, 'UPDATE', __FILE__);
        continue;
    }
    $user->query('COMMIT');
    $inbox->free();
    unset($inbox);
    if ($cache) {
        $cache->delete(Cache::key('user:notices_with_friends:' . $user->id));
    }
}

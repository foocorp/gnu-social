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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<END_OF_QUEUE_HELP
USAGE: handlequeued.php <queue> <notice id>
Run a single queued notice through background processing
as if it were being run through the queue.


END_OF_QUEUE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (count($args) != 2) {
    show_help();
}

$queue = trim($args[0]);
$noticeId = intval($args[1]);

$qm = QueueManager::get();
$handler = $qm->getHandler($queue);
if (!$handler) {
    print "No handler for queue '$queue'.\n";
    exit(1);
}

$notice = Notice::getKV('id', $noticeId);
if (empty($notice)) {
    print "Invalid notice id $noticeId\n";
    exit(1);
}

if (!$handler->handle($notice)) {
    print "Failed to handle notice id $noticeId on queue '$queue'.\n";
    exit(1);
}

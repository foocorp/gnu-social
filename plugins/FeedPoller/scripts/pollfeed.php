#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

$helptext = <<<END_OF_HELP
pollfeed.php feeduri

Poll the feed, assuming it has sub_state 'nohub'.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once(__DIR__ . '/../lib/feedpoll.php');

if (empty($args[0]) || !Validate::uri($args[0])) {
    echo "$helptext\n";
    exit(1);
}

$uri = $args[0];


$feedsub = FeedSub::getKV('uri', $uri);

if (!$feedsub instanceof FeedSub) {
    echo "No FeedSub feed known for URI $uri\n";
    exit(1);
}

if ($feedsub->sub_state != 'nohub') {
    echo "Feed is a PuSH feed, so we will not poll it.\n";
    exit(1);
}

showSub($feedsub);

try {
    FeedPoll::checkUpdates($feedsub);
} catch (Exception $e) {
    echo "Could not check updates for feed: ".$e->getMessage();
    echo $e->getTraceAsString();
    exit(1);
}

function showSub(FeedSub $sub)
{
    echo "  Subscription state: $sub->sub_state\n";
    echo "  Signature secret: $sub->secret\n";
    echo "  Sub start date: $sub->sub_start\n";
    echo "  Record created: $sub->created\n";
    echo "  Record modified: $sub->modified\n";
}

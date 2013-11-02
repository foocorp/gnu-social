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
resub-feed.php
Resubscribe to any soon expiring feeds. Default time to expiration is 1 day.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $sub = FeedSub::renewalCheck();
} catch (NoResultException $e) {
    echo 'There were no expiring feeds.';
    exit;
}

while ($sub->fetch()) {
    echo "Renewing feed subscription\n\tExp.: {$sub->sub_end}\n\tFeed: {$sub->uri}\n\tHub:  {$sub->huburi}\n";
    $sub->renew();
}

echo "Done!";

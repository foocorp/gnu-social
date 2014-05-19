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
gcfeeds.php [options]
Clean up feeds that no longer have subscribers.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$feedsub = new FeedSub();
$feedsub->find();
while ($feedsub->fetch()) {
    try {
        echo $feedsub->getUri() . " ({$feedsub->sub_state})";
        if ($feedsub->garbageCollect()) {
            echo " INACTIVE\n";
        } else {
            echo " ACTIVE\n";
        }
    } catch (NoProfileException $e) {
        echo " DELETED (no profile)\n";
        $feedsub->delete();
        continue;
    } catch (NoUriException $e) {
        // Probably the getUri() call
        echo "[unknown] DELETED (no uri)\n";
        $feedsub->delete();
        continue;
    } catch (Exception $e) {
        echo " ERROR: {$e->getMessage()}\n";
    }
}

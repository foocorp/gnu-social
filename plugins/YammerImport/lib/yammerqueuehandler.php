<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Queue handler for bumping the next chunk of Yammer import activity!
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class YammerQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'yammer';
    }

    function handle($notice)
    {
        $runner = YammerRunner::init();
        if ($runner->hasWork()) {
            try {
                if ($runner->iterate()) {
                    if ($runner->hasWork()) {
                        // More to do? Shove us back on the queue...
                        $runner->startBackgroundImport();
                    }
                }
            } catch (Exception $e) {
                try {
                    $runner->recordError($e->getMessage());
                } catch (Exception $f) {
                    common_log(LOG_ERR, "Error while recording error in Yammer background import: " . $e->getMessage() . " " . $f->getMessage());
                }
            }
        } else {
            // We're done!
            common_log(LOG_INFO, "Yammer import has no work to do at this time; discarding.");
        }
        return true;
    }
}

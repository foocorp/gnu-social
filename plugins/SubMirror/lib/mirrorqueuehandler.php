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

/**
 * Check for subscription mirroring options on each newly seen post!
 *
 * @package SubMirror
 * @author Brion Vibber <brion@status.net>
 */
class MirrorQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'mirror';
    }

    function handle($notice)
    {
        $mirror = new SubMirror();
        $mirror->subscribed = $notice->profile_id;
        if ($mirror->find()) {
            while ($mirror->fetch()) {
                try {
                    $mirror->mirrorNotice($notice);
                } catch (Exception $e) {
                    common_log(LOG_ERR, "Exception trying to mirror notice $notice->id " .
                                        "for subscriber $mirror->subscriber ($mirror->style): " .
                                        $e->getMessage());
                }
            }
        }
        return true;
    }
}

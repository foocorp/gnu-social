<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010-2011, StatusNet, Inc.
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
 * @package QueueHandler
 * @maintainer Brion Vibber <brion@status.net>
 */

class ProfileQueueHandler extends QueueHandler
{

    function transport()
    {
        return 'profile';
    }

    function handle($profile)
    {
        if (!($profile instanceof Profile)) {
            common_log(LOG_ERR, "Got a bogus profile, not broadcasting");
            return true;
        }

        if (Event::handle('StartBroadcastProfile', array($profile))) {
            require_once(dirname(__FILE__) . '/omb.php');
            try {
                omb_broadcast_profile($profile);
            } catch (Exception $e) {
                common_log(LOG_ERR, "Failed sending OMB profiles: " . $e->getMessage());
            }
        }
        Event::handle('EndBroadcastProfile', array($profile));
        return true;
    }

}

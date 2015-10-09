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
 * Send a Salmon notification in the background.
 * @package OStatusPlugin
 * @author Brion Vibber <brion@status.net>
 */
class SalmonQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'salmon';
    }

    function handle($data)
    {
        assert(is_array($data));
        assert(is_string($data['salmonuri']));
        assert(is_string($data['entry']));

        $actor = Profile::getKV($data['actor']);
        $target = array_key_exists('target', $data) ? Profile::getKV($data['target']) : null;

        Salmon::post($data['salmonuri'], $data['entry'], $actor, $target);

        // @fixme detect failure and attempt to resend
        return true;
    }
}

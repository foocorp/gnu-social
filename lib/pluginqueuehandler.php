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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Queue handler for letting plugins handle stuff.
 *
 * The plugin queue handler accepts notices over the "plugin" queue
 * and simply passes them through the "HandleQueuedNotice" event.
 *
 * This gives plugins a chance to do background processing without
 * actually registering their own queue and ensuring that things
 * are queued into it.
 *
 * Fancier plugins may wish to instead hook the 'GetQueueHandlerClass'
 * event with their own class, in which case they must ensure that
 * their notices get enqueued when they need them.
 */
class PluginQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'plugin';
    }

    function handle($notice)
    {
        try {
            Event::handle('HandleQueuedNotice', array(&$notice));
        } catch (UserNoProfileException $unp) {
            // We can't do anything about this, so just skip
            return true;
        }
        return true;
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Offline backup queue handler
 * 
 * PHP version 5
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
 *
 * @category  Offline backup
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Offline backup queue handler
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class OfflineBackupQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'backoff';
    }

    function handle($object)
    {
        $userId = $object;

        $user = User::staticGet($userId);

        $fileName = $this->makeBackupFile($user);

        $this->notifyBackupFile($fileName);

        return true;
    }

    function makeBackupFile($user)
    {
        $fileName = File::filename($user->getProfile(), "backup", "application/atom+xml");
        $fullPath = File::path($fileName);

        // XXX: this is pretty lose-y;  try another way

        $actstr = new UserActivityStream($user, true, UserActivityStream::OUTPUT_RAW);

        file_put_contents($fullPath, $actstr->getString());

        return $fileName;
    }

    function notifyBackupFile($fileName)
    {
        $fileUrl = 
    }
}

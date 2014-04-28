<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A class for moving an account to a new server
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
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Moves an account from this server to another
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AccountMover extends QueueHandler
{
    function transport()
    {
        return 'acctmove';
    }

    function handle($object)
    {
        list($user, $remote, $password) = $object;

        $remote = Discovery::normalize($remote);

        $oprofile = Ostatus_profile::ensureProfileURI($remote);

        if (empty($oprofile)) {
            // TRANS: Exception thrown when an account could not be located when it should be moved.
            // TRANS: %s is the remote site.
            throw new Exception(sprintf(_("Cannot locate account %s."),$remote));
        }

        list($svcDocUrl, $username) = self::getServiceDocument($remote);

        $sink = new ActivitySink($svcDocUrl, $username, $password);

        $this->log(LOG_INFO,
                   "Moving user {$user->nickname} ".
                   "to {$remote}.");

        $stream = new UserActivityStream($user);

        // Reverse activities to run in correct chron order

        $acts = array_reverse($stream->activities);

        $this->log(LOG_INFO,
                   "Got ".count($acts)." activities ".
                   "for {$user->nickname}.");

        $qm = QueueManager::get();

        foreach ($acts as $act) {
            $qm->enqueue(array($act, $sink, $user->getUri(), $remote), 'actmove');
        }

        $this->log(LOG_INFO,
                   "Finished moving user {$user->nickname} ".
                   "to {$remote}.");
    }

    static function getServiceDocument($remote)
    {
        $discovery = new Discovery();

        $xrd = $discovery->lookup($remote);

        if (empty($xrd)) {
            // TRANS: Exception thrown when a service document could not be located account move.
            // TRANS: %s is the remote site.
            throw new Exception(sprintf(_("Cannot find XRD for %s."),$remote));
        }

        $svcDocUrl = null;
        $username  = null;

        $link = $xrd->links->get('http://apinamespace.org/atom', 'application/atomsvc+xml');
        if (!is_null($link)) {
            $svcDocUrl = $link->href;
            if (isset($link['http://apinamespace.org/atom/username'])) {
                $username = $link['http://apinamespace.org/atom/username'];
                break;
            }
        }

        if (empty($svcDocUrl)) {
            // TRANS: Exception thrown when an account could not be located when it should be moved.
            // TRANS: %s is the remote site.
            throw new Exception(sprintf(_("No AtomPub API service for %s."),$remote));
        }

        return array($svcDocUrl, $username);
    }

    /**
     * Log some data
     *
     * Add a header for our class so we know who did it.
     *
     * @param int    $level   Log level, like LOG_ERR or LOG_INFO
     * @param string $message Message to log
     *
     * @return void
     */
    protected function log($level, $message)
    {
        common_log($level, "AccountMover: " . $message);
    }
}

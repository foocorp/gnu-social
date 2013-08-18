<?php
/**
 * Microsummary action, see https://wiki.mozilla.org/Microsummaries
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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
 * Microsummary action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class MicrosummaryAction extends Action
{
    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);

        $nickname = common_canonical_nickname($this->arg('nickname'));
        $user     = User::getKV('nickname', $nickname);

        if (!$user) {
            // TRANS: Client error displayed trying to make a micro summary without providing a valid user.
            $this->clientError(_('No such user.'), 404);
            return;
        }

        $notice = $user->getCurrentNotice();

        if (!$notice) {
            // TRANS: Client error displayed trying to make a micro summary without providing a status.
            $this->clientError(_('No current status.'), 404);
        }

        header('Content-Type: text/plain');

        print $user->nickname . ': ' . $notice->content;
    }

    function isReadOnly($args)
    {
        return true;
    }
}

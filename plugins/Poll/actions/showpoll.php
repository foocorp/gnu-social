<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Show a single Poll
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
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
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
 * Show a single Poll, with associated information
 *
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ShowPollAction extends ShownoticeAction
{
    protected $poll = null;

    function getNotice()
    {
        $this->id = $this->trimmed('id');

        $this->poll = Poll::getKV('id', $this->id);

        if (empty($this->poll)) {
            // TRANS: Client exception thrown trying to view a non-existing poll.
            throw new ClientException(_m('No such poll.'), 404);
        }

        $notice = $this->poll->getNotice();

        if (empty($notice)) {
            // Did we used to have it, and it got deleted?
            // TRANS: Client exception thrown trying to view a non-existing poll notice.
            throw new ClientException(_m('No such poll notice.'), 404);
        }

        return $notice;
    }

    /**
     * Title of the page
     *
     * Used by Action class for layout.
     *
     * @return string page tile
     */
    function title()
    {
        // TRANS: Page title for a poll.
        // TRANS: %1$s is the nickname of the user that created the poll, %2$s is the poll question.
        return sprintf(_m('%1$s\'s poll: %2$s'),
                       $this->user->nickname,
                       $this->poll->question);
    }

    /**
     * @fixme combine the notice time with poll update time
     */
    function lastModified()
    {
        return Action::lastModified();
    }


    /**
     * @fixme combine the notice time with poll update time
     */
    function etag()
    {
        return Action::etag();
    }
}

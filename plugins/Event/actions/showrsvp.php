<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Show a single RSVP
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
 * @category  RSVP
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
 * Show a single RSVP, with associated information
 *
 * @category  RSVP
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ShowrsvpAction extends ShownoticeAction
{
    protected $rsvp = null;
    protected $event = null;

    function getNotice()
    {
        $this->id = $this->trimmed('id');

        $this->rsvp = RSVP::getKV('id', $this->id);

        if (empty($this->rsvp)) {
            // TRANS: Client exception thrown when referring to a non-existing RSVP.
            // TRANS: RSVP stands for "Please reply".
            throw new ClientException(_m('No such RSVP.'), 404);
        }

        $this->event = $this->rsvp->getEvent();

        if (empty($this->event)) {
            // TRANS: Client exception thrown when referring to a non-existing event.
            throw new ClientException(_m('No such event.'), 404);
        }

        $notice = $this->rsvp->getNotice();

        if (empty($notice)) {
            // Did we used to have it, and it got deleted?
            // TRANS: Client exception thrown when referring to a non-existing RSVP.
            // TRANS: RSVP stands for "Please reply".
            throw new ClientException(_m('No such RSVP.'), 404);
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
        // TRANS: Title for event.
	// TRANS: %1$s is a user nickname, %2$s is an event title.
        return sprintf(_m('%1$s\'s RSVP for "%2$s"'),
                       $this->user->nickname,
                       $this->event->title);
    }
}

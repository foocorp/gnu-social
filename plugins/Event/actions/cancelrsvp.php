<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Cancel the RSVP for an event
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
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * RSVP for an event
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class CancelrsvpAction extends FormAction
{
    protected $form = 'CancelRSVP';

    function title()
    {
        // TRANS: Title for RSVP ("please respond") action.
        return _m('TITLE','Cancel RSVP');
    }

    // FIXME: Merge this action with RSVPAction and add a 'cancel' thing there...
    protected function doPreparation()
    {
        $rsvpId = $this->trimmed('rsvp');
        if (empty($rsvpId)) {
            // TRANS: Client exception thrown when referring to a non-existing RSVP ("please respond") item.
            throw new ClientException(_m('No such RSVP.'));
        }

        $this->rsvp = RSVP::getKV('id', $rsvpId);
        if (empty($this->rsvp)) {
            // TRANS: Client exception thrown when referring to a non-existing RSVP ("please respond") item.
            throw new ClientException(_m('No such RSVP.'));
        }

        $this->formOpts['rsvp'] = $this->rsvp;
    }

    protected function doPost()
    {
        $this->event = Happening::getKV('uri', $this->rsvp->event_uri);
        if (empty($this->event)) {
            // TRANS: Client exception thrown when referring to a non-existing event.
            throw new ClientException(_m('No such event.'));
        }

        $notice = $this->rsvp->getNotice();
        // NB: this will delete the rsvp, too
        if (!empty($notice)) {
            common_log(LOG_DEBUG, "Deleting notice...");
            $notice->deleteAs($this->scoped);
        } else {
            common_log(LOG_DEBUG, "Deleting RSVP alone...");
            $this->rsvp->delete();
        }
    }
}

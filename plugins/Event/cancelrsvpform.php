<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form to RSVP for an event
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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A form to RSVP for an event
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class CancelRSVPForm extends Form
{
    protected $rsvp = null;

    function __construct($rsvp, $out=null)
    {
        parent::__construct($out);
        $this->rsvp = $rsvp;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_event_rsvp';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'ajax';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('cancelrsvp');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'new_rsvp_data'));

        $this->out->hidden('rsvp-id', $this->rsvp->id, 'rsvp');

        switch (RSVP::verbFor($this->rsvp->response)) {
        case RSVP::POSITIVE:
            // TRANS: Possible status for RSVP ("please respond") item.
            $this->out->text(_m('You will attend this event.'));
            break;
        case RSVP::NEGATIVE:
            // TRANS: Possible status for RSVP ("please respond") item.
            $this->out->text(_m('You will not attend this event.'));
            break;
        case RSVP::POSSIBLE:
            // TRANS: Possible status for RSVP ("please respond") item.
            $this->out->text(_m('You might attend this event.'));
            break;
        }

        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text to cancel responding to an RSVP ("please respond") item.
        $this->out->submit('rsvp-cancel', _m('BUTTON', 'Cancel'));
    }
}

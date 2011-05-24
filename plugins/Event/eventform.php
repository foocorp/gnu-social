<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form for entering an event
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
 * Form for adding an event
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EventForm extends Form
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_new_event';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings ajax-notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('newevent');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'new_event_data'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->input('event-title',
                          // TRANS: Field label on event form.
                          _m('LABEL','Title'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Title of the event.'),
                          'title');
        $this->unli();

        $this->li();
        $this->out->input('event-startdate',
                          // TRANS: Field label on event form.
                          _m('LABEL','Start date'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Date the event starts.'),
                          'startdate');
        $this->unli();

        $this->li();
        $this->out->input('event-starttime',
                          // TRANS: Field label on event form.
                          _m('LABEL','Start time'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Time the event starts.'),
                          'starttime');
        $this->unli();

        $this->li();
        $this->out->input('event-enddate',
                          // TRANS: Field label on event form.
                          _m('LABEL','End date'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Date the event ends.'),
                          'enddate');
        $this->unli();

        $this->li();
        $this->out->input('event-endtime',
                          // TRANS: Field label on event form.
                          _m('LABEL','End time'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Time the event ends.'),
                          'endtime');
        $this->unli();

        $this->li();
        $this->out->input('event-location',
                          // TRANS: Field label on event form.
                          _m('LABEL','Location'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Event location.'),
                          'location');
        $this->unli();

        $this->li();
        $this->out->input('event-url',
                          // TRANS: Field label on event form.
                          _m('LABEL','URL'),
                          null,
                          // TRANS: Field title on event form.
                          _m('URL for more information.'),
                          'url');
        $this->unli();

        $this->li();
        $this->out->input('event-description',
                          // TRANS: Field label on event form.
                          _m('LABEL','Description'),
                          null,
                          // TRANS: Field title on event form.
                          _m('Description of the event.'),
                          'description');
        $this->unli();

        $this->out->elementEnd('ul');

        $toWidget = new ToSelector($this->out,
                                   common_current_user(),
                                   null);
        $toWidget->show();

        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text to save an event..
        $this->out->submit('event-submit', _m('BUTTON', 'Save'), 'submit', 'submit');
    }
}

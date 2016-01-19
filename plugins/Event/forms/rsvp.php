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

if (!defined('GNUSOCIAL')) { exit(1); }

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
class RSVPForm extends Form
{
    protected $event = null;

    function __construct($out=null, array $formOpts=array())
    {
        parent::__construct($out);
        if (!isset($formOpts['event'])) {
            throw new ServerException('You must set the "event" form option for RSVPForm.');
        } elseif (!$formOpts['event'] instanceof Happening) {
            throw new ServerException('The "event" form option for RSVPForm must be a Happening object.');
        }
        $this->event = $formOpts['event'];
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
        return common_local_url('newrsvp');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'new_rsvp_data'));

        // TRANS: Field label on form to RSVP ("please respond") for an event.
        $this->out->text(_m('RSVP:'));

        $this->out->hidden('event-id', $this->event->getUri(), 'event');
        $this->out->hidden('submitvalue', '');

        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text for RSVP ("please respond") reply to confirm attendence.
        $this->submitButton('yes', _m('BUTTON', 'Yes'));
        // TRANS: Button text for RSVP ("please respond") reply to deny attendence.
        $this->submitButton('no', _m('BUTTON', 'No'));
        // TRANS: Button text for RSVP ("please respond") reply to indicate one might attend.
        $this->submitButton('maybe', _m('BUTTON', 'Maybe'));
    }

    function submitButton($id, $label)
    {
        $this->out->element(
            'input',
                array(
                    'type'    => 'submit',
                    'id'      => 'rsvp-submit',
                    'name'    => $id,
                    'class'   => 'submit',
                    'value'   => $label,
                    'title'   => $label,
                    'onClick' => 'this.form.submitvalue.value = this.name; return true;'
            )
        );
    }
}

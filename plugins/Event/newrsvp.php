<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * RSVP for an event
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
 * RSVP for an event
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewrsvpAction extends Action
{
    protected $user  = null;
    protected $event = null;
    protected $verb  = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Title for RSVP ("please respond") action.
        return _m('TITLE','New RSVP');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true); // short error results!
        }

        $eventId = $this->trimmed('event');

        if (empty($eventId)) {
            // TRANS: Client exception thrown when referring to a non-existing event.
            throw new ClientException(_m('No such event.'));
        }

        $this->event = Happening::staticGet('id', $eventId);

        if (empty($this->event)) {
            // TRANS: Client exception thrown when referring to a non-existing event.
            throw new ClientException(_m('No such event.'));
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown when trying to RSVP ("please respond") while not logged in.
            throw new ClientException(_m('You must be logged in to RSVP for an event.'));
        }

        common_debug(print_r($this->args, true));

        switch (strtolower($this->trimmed('submitvalue'))) {
        case 'yes':
            $this->verb = RSVP::POSITIVE;
            break;
        case 'no':
            $this->verb = RSVP::NEGATIVE;
            break;
        case 'maybe':
            $this->verb = RSVP::POSSIBLE;
            break;
        default:
            // TRANS: Client exception thrown when using an invalid value for RSVP ("please respond").
            throw new ClientException(_m('Unknown submit value.'));
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->newRSVP();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new event
     *
     * @return void
     */
    function newRSVP()
    {
        try {
            $saved = RSVP::saveNew($this->user->getProfile(),
                                   $this->event,
                                   $this->verb);
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            $rsvp = RSVP::fromNotice($saved);
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after creating an event.
            $this->element('title', null, _m('Event saved'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->elementStart('body');
            $cancel = new CancelRSVPForm($rsvp, $this);
            $cancel->show();
            $this->elementEnd('body');
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($saved->bestUrl(), 303);
        }
    }

    /**
     * Show the event form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new RSVPForm($this->event, $this);

        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}

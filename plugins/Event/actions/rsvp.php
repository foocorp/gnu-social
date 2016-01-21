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
class RsvpAction extends FormAction
{
    protected $form = 'RSVP';

    protected $event = null;

    function title()
    {
        // TRANS: Title for RSVP ("please respond") action.
        return _m('TITLE','New RSVP');
    }

    protected function doPreparation()
    {
        if ($this->trimmed('notice')) {
            $stored = Notice::getByID($this->trimmed('notice'));
            $this->event = Happening::fromStored($stored);
        } else {
            $this->event = Happening::getByKeys(['uri'=>$this->trimmed('event')]);
        }

        $this->formOpts['event'] = $this->event;
    }

    protected function doPost()
    {
        if ($this->trimmed('rsvp') === 'cancel') {
            $rsvp = RSVP::byEventAndActor($this->event, $this->scoped);
            try {
                $notice = $rsvp->getStored();
                $notice->deleteAs($this->scoped);
            } catch (NoResultException $e) {
                // Notice already gone
                $rsvp->delete();
            }
            return _m('Cancelled RSVP');
        }

        $verb = RSVP::verbFor(strtolower($this->trimmed('rsvp')));
        $options = array('source' => 'web');

        $act = new Activity();
        $act->id = UUID::gen();
        $act->verb    = $verb;
        $act->time    = time();
        $act->title   = _m('RSVP');
        $act->actor   = $this->scoped->asActivityObject();
        $act->target  = $this->event->getStored()->asActivityObject();
        $act->objects = array(clone($act->target));
        $act->content = RSVP::toHTML($this->scoped, $this->event, RSVP::codeFor($verb));

        $act->context = new ActivityContext();
        $act->context->replyToID = $this->event->getUri();

        $stored = Notice::saveActivity($act, $this->scoped, $options);

        return _m('Saved RSVP');
    }
}

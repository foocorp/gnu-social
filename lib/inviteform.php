<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for inviting collegues and friends
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Form
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for inviting collegues and friends
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class InviteForm extends Form
{
    /**
     * Constructor
     *
     * @param Action $out output channel
     */
    function __construct($out=null)
    {
        parent::__construct($out);
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
       return 'form_invite';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('invite');
    }

    /**
     * Name of the form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend.
        $this->out->element('legend', null, _('Invite collegues'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('ul', 'form_data');
        $this->out->elementStart('li');
        $this->out->textarea(
            'addresses',
            // TRANS: Field label for a list of e-mail addresses.
            _('Email addresses'),
            $this->out->trimmed('addresses'),
            // TRANS: Field title for a list of e-mail addresses.
            _('Addresses of friends to invite (one per line).')
        );
        $this->out->elementEnd('li');
        $this->out->elementStart('li');
        $this->out->textarea(
            // TRANS: Field label for a personal message to send to invitees.
            'personal', _('Personal message'),
            $this->out->trimmed('personal'),
            // TRANS: Field title for a personal message to send to invitees.
            _('Optionally add a personal message to the invitation.')
        );
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit(
            'send',
            // TRANS: Send button for inviting friends
            _m('BUTTON','Send'), 'submit form_action-primary',
            'send',
            // TRANS: Submit button title.
            _('Send invitations.')
        );
    }
}

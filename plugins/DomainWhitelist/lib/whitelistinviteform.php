<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Fancy form for inviting collegues
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
 *
 */
class WhitelistInviteForm extends Form
{
    private $whitelist = null;

    /**
     * Constructor
     *
     * @param Action $out output channel
     */
    function __construct($out, $whitelist)
    {
        parent::__construct($out);
        $this->whitelist = $whitelist;
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
        $this->out->element('legend', null, _m('Invite collegues'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('ul', 'form_data');
        for ($i = 0; $i < 3; $i++) {
            $this->showEmailLI();
        }
        $this->out->elementStart('li');
        $this->out->textarea(
            // TRANS: Field label for a personal message to send to invitees.
            'personal', _m('Personal message'),
            $this->out->trimmed('personal'),
            // TRANS: Field title for a personal message to send to invitees.
            _m('Optionally add a personal message to the invitation.')
        );
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
    }

    function showEmailLI()
    {
        $this->out->elementStart('li');
        $this->out->input('username[]', '');
        $this->out->text('@');
        if (count($this->whitelist) == 1) {
            $this->out->element(
                'span',
                array('class' => 'email_invite'),
                $this->whitelist[0]
           );
           $this->out->hidden('domain[]', $this->whitelist[0]);
        } else {
            $content = array();
            foreach($this->whitelist as $domain) {
                $content[$domain] = $domain;
            }
            $this->out->dropdown('domain[]', '', $content);
        }
        $this->showMultiControls();
        $this->out->elementEnd('li');
    }

    function showMultiControls()
    {
        $this->out->element(
            'a',
            array(
                'class' => 'remove_row',
                'href'  => 'javascript://',
                'style' => 'display: none;'
            ),
            '-'
        );

        $this->out->element(
            'a',
            array(
                'class' => 'add_row',
                'href'  => 'javascript://',
                'style' => 'display: none;'
            ),
            // TRANS: Link description to action to add another item to a list.
            _m('Add another item')
        );
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
            // TRANS: Send button for inviting friends.
            _m('BUTTON','Send'), 'submit form_action-primary',
            'send',
            // TRANS: Submit button title.
            _m('Send invitations.')
        );
    }
}

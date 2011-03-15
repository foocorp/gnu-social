<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Action for confirming first email registration
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
 * @category  Confirmation
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
 * Class comment
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ConfirmfirstemailAction extends Action
{
    public $confirm;
    public $code;
    public $password;
    public $user;

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
        $user = common_current_user();

        if (!empty($user)) {
            throw new ClientException(_('You are already logged in.'));
        }

        $this->code = $this->trimmed('code');

        $this->confirm = Confirm_address::staticGet('code', $this->code);

        if (empty($this->confirm)) {
            throw new ClientException(_('Confirmation code not found.'));
            return;
        }

        $this->user = User::staticGet('id', $this->confirm->user_id);

        if (empty($this->user)) {
            throw new ServerException(_('No user for that confirmation code.'));
        }

        $type = $this->confirm->address_type;

        if ($type != 'email') {
            throw new ServerException(sprintf(_('Unrecognized address type %s.'), $type));
        }

        if (!empty($this->user->email) && $this->user->email == $confirm->address) {
            // TRANS: Client error for an already confirmed email/jabber/sms address.
            throw new ClientException(_('That address has already been confirmed.'));
        }

        if ($this->isPost()) {

            $this->checkSessionToken();

            $password = $this->trimmed('password');
            $confirm  = $this->trimmed('confirm');

            if (strlen($password) < 6) {
                throw new ClientException(_('Password too short.'));
                return;
            } else if (0 != strcmp($password, $confirm)) {
                throw new ClientException(_("Passwords don't match."));
                return;
            }

            $this->password = $password;
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
        $homepage = common_local_url('all', 
                                     array('nickname' => $this->user->nickname));

        if ($this->isPost()) {
            $this->confirmUser();
            common_set_user($this->user);
            common_real_login(true);
            common_redirect($homepage, 303);
        } else {
            $this->showPage();
        }
        return;
    }

    function confirmUser()
    {
        $orig = clone($this->user);

        $this->user->email = $this->confirm->address;

        $this->user->updateKeys($orig);

        $this->user->emailChanged();

        $orig = clone($this->user);

        $this->user->password = common_munge_password($this->password, $this->user->id);

        $this->user->update($orig);

        $this->confirm->delete();
    }

    function showContent()
    {
        $this->element('p', 'instructions',
                       sprintf(_('You have confirmed the email address for your new user account %s. '.
                                 'Use the form below to set your new password.'),
                               $this->user->nickname));

        $form = new ConfirmFirstEmailForm($this, $this->code);
        $form->show();
    }

    function title()
    {
        return _('Set a password');
    }
}

class ConfirmFirstEmailForm extends Form
{
    public $code;

    function __construct($out, $code)
    {
        parent::__construct($out);
        $this->code = $code;
    }

    function formLegend()
    {
        return _('Confirm email');
    }

    function action()
    {
        return common_local_url('confirmfirstemail',
                                array('code' => $this->code));
    }

    function formClass()
    {
        return 'form_settings';
    }

    function formData()
    {
        $this->out->elementStart('ul', 'form_data');
        $this->out->elementStart('li');
        $this->out->password('password', _('New password'),
                             _('6 or more characters.'));
        $this->out->elementEnd('li');
        $this->out->elementStart('li');
        $this->out->password('confirm', _('Confirm'),
                             _('Same as password above.'));
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
    }

    function formActions()
    {
        $this->out->submit('save', _('Save'));
    }
}

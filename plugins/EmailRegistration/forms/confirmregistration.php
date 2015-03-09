<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Registration confirmation form
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
 * @category  Email registration
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
 * Registration confirmation form
 *
 * @category  Email registration
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ConfirmRegistrationForm extends Form
{
    protected $code;
    protected $nickname;
    protected $email;

    function __construct($out, $nickname, $email, $code)
    {
        parent::__construct($out);
        $this->nickname = $nickname;
        $this->email = $email;
        $this->code = $code;
    }

    function formData()
    {
        $this->out->element('p', 'instructions',
                            // TRANS: Form instructions.
                            sprintf(_m('Enter a password to confirm your new account.')));

        $this->hidden('code', $this->code);

        $this->out->elementStart('ul', 'form_data');

        $this->elementStart('li');

        // TRANS: Field label in e-mail registration form.
        $this->element('label', array('for' => 'nickname'), _m('LABEL','User name'));

        $this->element('input', array('name' => 'nickname',
                                      'type' => 'text',
                                      'id' => 'nickname',
                                      'value' => $this->nickname));

        $this->elementEnd('li');

        $this->elementStart('li');

        // TRANS: Field label.
        $this->element('label', array('for' => 'email-ignore'), _m('Email address'));

        $this->element('input', array('name' => 'email-ignore',
                                      'type' => 'text',
                                      'id' => 'email-ignore',
                                      'disabled' => 'true',
                                      'value' => $this->email));

        $this->elementEnd('li');

        $this->elementStart('li');
        // TRANS: Field label on account registration page.
        $this->password('password1', _m('Password'),
                        // TRANS: Field title on account registration page.
                        _m('6 or more characters.'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label on account registration page. In this field the password has to be entered a second time.
        $this->password('password2', _m('PASSWORD','Confirm'),
                        // TRANS: Field title on account registration page.
                        _m('Same as password above.'));
        $this->elementEnd('li');

        $this->elementStart('li');

        $this->element('input', array('name' => 'tos',
                                      'type' => 'checkbox',
                                      'class' => 'checkbox',
                                      'id' => 'tos',
                                      'value' => 'true'));
        $this->text(' ');

        $this->elementStart('label', array('class' => 'checkbox',
                                           'for' => 'tos'));

        // TRANS: Checkbox title for terms of service and privacy policy.
        $this->raw(sprintf(_m('I agree to the <a href="%1$s">Terms of service</a> and '.
                             '<a href="%1$s">Privacy policy</a> of this site.'),
                           common_local_url('doc', array('title' => 'tos')),
                           common_local_url('doc', array('title' => 'privacy'))));

        $this->elementEnd('label');

        $this->elementEnd('li');

        $this->out->elementEnd('ul');
    }

    function method()
    {
        return 'post';
    }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     *
     * @return void
     */

    function formActions()
    {
        // TRANS: Button text for action to register.
        $this->out->submit('submit', _m('BUTTON', 'Register'));
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_email_registration';
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     *
     * @return string URL to post to
     */

    function action()
    {
        return common_local_url('register');
    }

    function formClass()
    {
        return 'form_confirm_registration form_settings';
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Email registration form
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
 * Email registration form
 *
 * @category  Email registration
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EmailRegistrationForm extends Form
{
    protected $email;

    function __construct($out, $email)
    {
        parent::__construct($out);
        $this->email = $email;
    }

    function formData()
    {
        $this->out->element('p', 'instructions',
                            // TRANS: Form instructions.
                            _m('Enter your email address to register for an account.'));

        $this->out->elementStart('fieldset', array('id' => 'new_bookmark_data'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->input('email',
                          // TRANS: Field label on form for registering an account.
                          _m('LABEL','E-mail address'),
                          $this->email);
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
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
        // TRANS: Button text for registering an account.
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
        return 'form_email_registration form_settings';
    }
}

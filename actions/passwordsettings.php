<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Change user password
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) { exit(1); }

/**
 * Change password
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PasswordsettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Title for page where to change password.
        return _m('TITLE','Change password');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instructions for page where to change password.
        return _('Change your password.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('oldpassword');
    }

    function showContent()
    {
        $this->elementStart('form', array('method' => 'POST',
                                          'id' => 'form_password',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('passwordsettings')));
        $this->elementStart('fieldset');
        // TRANS: Fieldset legend on page where to change password.
        $this->element('legend', null, _('Password change'));
        $this->hidden('token', common_session_token());


        $this->elementStart('ul', 'form_data');
        // Users who logged in with OpenID won't have a pwd
        if ($this->scoped->hasPassword()) {
            $this->elementStart('li');
            // TRANS: Field label on page where to change password.
            $this->password('oldpassword', _('Old password'));
            $this->elementEnd('li');
        }
        $this->elementStart('li');
        // TRANS: Field label on page where to change password.
        $this->password('newpassword', _('New password'),
                        // TRANS: Field title on page where to change password.
                        _('6 or more characters.'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label on page where to change password. In this field the new password should be typed a second time.
        $this->password('confirm', _m('LABEL','Confirm'),
                        // TRANS: Field title on page where to change password.
                        _('Same as password above.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        // TRANS: Button text on page where to change password.
        $this->submit('changepass', _m('BUTTON','Change'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    protected function doPost()
    {
        // FIXME: scrub input

        $newpassword = $this->arg('newpassword');
        $confirm     = $this->arg('confirm');

        // Some validation

        if (strlen($newpassword) < 6) {
            // TRANS: Form validation error on page where to change password.
            throw new ClientException(_('Password must be 6 or more characters.'));
        } else if (0 != strcmp($newpassword, $confirm)) {
            // TRANS: Form validation error on password change when password confirmation does not match.
            throw new ClientException(_('Passwords do not match.'));
        }

        $oldpassword = null;
        if ($this->scoped->hasPassword()) {
            $oldpassword = $this->arg('oldpassword');

            if (!common_check_user($this->scoped->getNickname(), $oldpassword)) {
                // TRANS: Form validation error on page where to change password.
                throw new ClientException(_('Incorrect old password.'));
            }
        }

        if (Event::handle('StartChangePassword', array($this->scoped, $oldpassword, $newpassword))) {
            //no handler changed the password, so change the password internally
            $user = $this->scoped->getUser();
            $original = clone($user);

            $user->password = common_munge_password($newpassword, $this->scoped);

            $val = $user->validate();
            if ($val !== true) {
                // TRANS: Form validation error on page where to change password.
                throw new ServerException(_('Error saving user; invalid.'));
            }

            if (!$user->update($original)) {
                // TRANS: Server error displayed on page where to change password when password change
                // TRANS: could not be made because of a server error.
                throw new ServerException(_('Cannot save new password.'));
            }
            Event::handle('EndChangePassword', array($this->scoped));
        }

        // TRANS: Form validation notice on page where to change password.
        return _('Password saved.');
    }
}

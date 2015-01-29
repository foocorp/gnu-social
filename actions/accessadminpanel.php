<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Site access administration panel
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Administer site access settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AccessadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for Access admin panel that allows configuring site access.
        return _('Access');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Page notice.
        return _('Site access settings');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new AccessAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save settings from the form
     *
     * @return void
     */
    function saveSettings()
    {
        static $booleans = array('site' => array('private', 'inviteonly', 'closed'),
                                 'public' => array('localonly'));

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = ($this->boolean($setting)) ? 1 : 0;
            }
        }

        $config = new Config();

        $config->query('BEGIN');

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        $config->query('COMMIT');

        return;
    }
}

class AccessAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_site_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('accessadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'settings_admin_account_access'));
        // TRANS: Form legend for registration form.
        $this->out->element('legend', null, _('Registration'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        // TRANS: Checkbox instructions for admin setting "Invite only".
        $instructions = _('Make registration invitation only.');
        // TRANS: Checkbox label for configuring site as invite only.
        $this->out->checkbox('inviteonly', _('Invite only'),
                             (bool) $this->value('inviteonly'),
                             $instructions);
        $this->unli();

        $this->li();
        // TRANS: Checkbox instructions for admin setting "Closed" (no new registrations).
        $instructions = _('Disable new registrations.');
        // TRANS: Checkbox label for disabling new user registrations.
        $this->out->checkbox('closed', _('Closed'),
                             (bool) $this->value('closed'),
                             $instructions);
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');


        // Public access settings (login requirements for feeds etc.)
	    $this->out->elementStart('fieldset', array('id' => 'settings_admin_public_access'));
	    // TRANS: Form legend for registration form.
        $this->out->element('legend', null, _('Feed access'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        // TRANS: Checkbox instructions for admin setting "Private".
        $instructions = _('Prohibit anonymous users (not logged in) from viewing site?');
        // TRANS: Checkbox label for prohibiting anonymous users from viewing site.
        $this->out->checkbox('private', _m('LABEL', 'Private'),
                             (bool) $this->value('private'),
                             $instructions);
        $this->unli();

        $this->li();
        // TRANS: Description of the full network notice stream views..
        $instructions = _('The full network view includes (public) remote notices which may be unrelated to local conversations.');
        // TRANS: Checkbox label for hiding remote network posts if they have not been interacted with locally.
        $this->out->checkbox('localonly', _('Restrict full network view to accounts'),
                             (bool) $this->value('localonly', 'public'),
                             $instructions);
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button title to save access settings in site admin panel.
        $title = _('Save access settings.');
        // TRANS: Button text to save access settings in site admin panel.
        $this->out->submit('submit', _m('BUTTON', 'Save'), 'submit', null, $title);
    }
}

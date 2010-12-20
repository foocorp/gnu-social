<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * License administration panel
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * License settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class LicenseadminpanelAction extends AdminPanelAction
{

    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        // TRANS: User admin panel title
        return _m('TITLE', 'License');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('License for this StatusNet site');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new LicenseAdminPanelForm($this);
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
        static $settings = array(
            'license' => array('type', 'owner', 'url', 'title', 'image')
        );

        $values = array();

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = $this->trimmed($setting);
            }
        }

        // This throws an exception on validation errors

        $this->validate($values);

        // assert(all values are valid);

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        $config->query('COMMIT');

        return;
    }

    /**
     * Validate License admin form values
     *
     * @param array &$values from the form
     *
     * @return nothing
     */

    function validate(&$values)
    {
        // Validate license type (shouldn't have to do it, but just in case)

        $types = array('private', 'allrightsreserved', 'cc');

        if (!in_array($values['license']['type'], $types)) {
            $this->clientError(_("Invalid license selection."));
        }

        // Make sure the user has set an owner if the site has a private
        // license

        if ($values['license']['type'] == 'allrightsreserved'
            && empty($values['license']['owner'])
        ) {
            $this->clientError(
                _("You must specify the owner of the content when using the All Rights Reserved license.")
            );
        }

        // Make sure the license title is not too long
        if (mb_strlen($values['license']['type']) > 255) {
            $this->clientError(
                _('Invalid license title. Maximum length is 255 characters.')
            );
        }

        // make sure the license URL and license image URL are valid URLs

        $options = array('allowed_schemes' => array('http', 'https'));

        // URLs should be set for cc license

        if ($values['license']['type'] == 'cc') {
            if (!Validate::uri($values['license']['url'], $options)) {
                $this->clientError(_("Invalid license URL."));
            }
            if (!Validate::uri($values['license']['image'], $options)) {
                $this->clientError(_("Invalid license image URL."));
            }
        }

        // can be either blank or a valid URL for private & allrightsreserved

        if (!empty($values['license']['url'])) {
            if (!Validate::uri($values['license']['url'], $options)) {
                $this->clientError(_("License URL must be blank or a valid URL."));
            }
        }

        // can be either blank or a valid URL for private & allrightsreserved

        if (!empty($values['license']['image'])) {
            if (!Validate::uri($values['license']['image'], $options)) {
                $this->clientError(_("License image must be blank or valid URL."));
            }
        }
    }
}

class LicenseAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'licenseadminpanel';
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
        return common_local_url('licenseadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->elementStart(
            'fieldset', array('id' => 'settings_license-selection')
        );
        $this->out->element('legend', null, _('License selection'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();

        $types = array(
            'private' => _('Private'),
            'allrightsreserved' => _('All Rights Reserved'),
            'cc' => _('Creative Commons')
        );

        $this->out->dropdown(
            'type',
            _('Type'),
            $types,
            _('Select license'),
            false,
            $this->value('type', 'license')
        );

        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart(
            'fieldset',
            array('id' => 'settings_license-details')
        );
        $this->out->element('legend', null, _('License details'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input(
            'owner',
            _('Owner'),
            _('Name of the owner of the site\'s content (if applicable).'),
            'license'
        );
        $this->unli();

        $this->li();
        $this->input(
            'title',
            _('License Title'),
            _('The title of the license.'),
            'license'
        );
        $this->unli();

        $this->li();
        $this->input(
            'url',
            _('License URL'),
            _('URL for more information about the license.'),
            'license'
        );
        $this->unli();

        $this->li();
        $this->input(
            'image', _('License Image URL'),
            _('URL for an image to display with the license.'),
            'license'
        );
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
        $this->out->submit(
            'submit', _('Save'), 'submit', null, _('Save license settings')
        );
    }
}

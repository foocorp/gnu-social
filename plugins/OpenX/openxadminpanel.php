<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * OpenX administration panel
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
 * @category  OpenX
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer openx settings
 *
 * @category OpenX
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class OpenXadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for OpenX admin panel.
        return _m('TITLE', 'OpenX');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for OpenX admin panel.
        return _m('OpenX settings for this StatusNet site');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new OpenXAdminPanelForm($this);
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
        static $settings = array('openx' => array('adScript', 'mediumRectangle', 'rectangle', 'leaderboard', 'wideSkyscraper'));

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

    function validate(&$values)
    {
    }
}

/**
 * Form for the openx admin panel
 */
class OpenXAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_openx_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_openx';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('openxadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'openx_admin'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $this->input('adScript',
                     // TRANS: Form label in OpenX admin panel.
                     _m('Ad script URL'),
                     // TRANS: Tooltip for form label in OpenX admin panel.
                     _m('Script URL'),
                     'openx');
        $this->unli();
        $this->li();
        $this->input('mediumRectangle',
                     // TRANS: Form label in OpenX admin panel. Refers to advertisement format.
                     _m('Medium rectangle'),
                     // TRANS: Tooltip for form label in OpenX admin panel. Refers to advertisement format.
                     _m('Medium rectangle zone'),
                     'openx');
        $this->unli();
        $this->li();
        $this->input('rectangle',
                     // TRANS: Form label in OpenX admin panel. Refers to advertisement format.
                     _m('Rectangle'),
                     // TRANS: Tooltip for form label in OpenX admin panel. Refers to advertisement format.
                     _m('Rectangle zone'),
                     'openx');
        $this->unli();
        $this->li();
        $this->input('leaderboard',
                     // TRANS: Form label in OpenX admin panel. Refers to advertisement format.
                     _m('Leaderboard'),
                     // TRANS: Tooltip for form label in OpenX admin panel. Refers to advertisement format.
                     _m('Leaderboard zone'),
                     'openx');
        $this->unli();
        $this->li();
        $this->input('wideSkyscraper',
                     // TRANS: Form label in OpenX admin panel. Refers to advertisement format.
                     _m('Skyscraper'),
                     // TRANS: Tooltip for form label in OpenX admin panel. Refers to advertisement format.
                     _m('Wide skyscraper zone'),
                     'openx');
        $this->unli();
        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                    // TRANS: Submit button text in OpenX admin panel.
                    _m('BUTTON','Save'),
                    'submit',
                    null,
                    // TRANS: Submit button title in OpenX admin panel.
                    _m('Save OpenX settings.'));
    }
}

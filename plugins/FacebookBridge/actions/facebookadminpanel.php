<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Facebook integration administration panel
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
 * Administer global Facebook integration settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class FacebookadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        return _m('Facebook');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        return _m('Facebook integration settings');
    }

    /**
     * Show the Facebook admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new FacebookAdminPanelForm($this);
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
            'facebook' => array('appid', 'secret'),
        );

        $values = array();

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting]
                    = $this->trimmed($setting);
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
        // appId, key and secret (can't be too long)

        if (mb_strlen($values['facebook']['appid']) > 255) {
            $this->clientError(
                _m("Invalid Facebook ID. Max length is 255 characters.")
            );
        }

        if (mb_strlen($values['facebook']['secret']) > 255) {
            $this->clientError(
                _m("Invalid Facebook secret. Max length is 255 characters.")
            );
        }
    }
}

class FacebookAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'facebookadminpanel';
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
        return common_local_url('facebookadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart(
            'fieldset',
            array('id' => 'settings_facebook-application')
        );
        $this->out->element('legend', null, _m('Facebook application settings'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input(
            'appid',
            _m('Application ID'),
            _m('ID of your Facebook application'),
            'facebook'
        );
        $this->unli();

        $this->li();
        $this->input(
            'secret',
             _m('Secret'),
            _m('Application secret'),
            'facebook'
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
        $this->out->submit('submit', _m('Save'), 'submit', null, _m('Save Facebook settings'));
    }
}

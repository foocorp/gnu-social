<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Admin panel for plugin to use bit.ly URL shortening services.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer global bit.ly URL shortener settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class BitlyadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title of administration panel.
        return _m('bit.ly URL shortening');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for administration panel.
        // TRANS: This message contains Markdown links in the form [decsription](link).
        return _m('URL shortening with bit.ly requires ' .
                  '[a bit.ly account and API key](http://bit.ly/a/your_api_key). ' .
                  'This verifies that this is an authorized account, and ' .
                  'allow you to use bit.ly\'s tracking features and custom domains.');
    }

    /**
     * Show the bit.ly admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new BitlyAdminPanelForm($this);
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
            'bitly' => array('default_login', 'default_apikey')
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
        // Validate consumer key and secret (can't be too long)

        if (mb_strlen($values['bitly']['default_apikey']) > 255) {
            $this->clientError(
                // TRANS: Client error displayed when using too long a key.
                _m('Invalid login. Maximum length is 255 characters.')
            );
        }

        if (mb_strlen($values['bitly']['default_apikey']) > 255) {
            $this->clientError(
                // TRANS: Client error displayed when using too long a key.
                _m('Invalid API key. Maximum length is 255 characters.')
            );
        }
    }
}

class BitlyAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'bitlyadminpanel';
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
        return common_local_url('bitlyadminpanel');
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
            array('id' => 'settings_bitly')
        );
        // TRANS: Fieldset legend in administration panel for bit.ly username and API key.
        $this->out->element('legend', null, _m('LEGEND','Credentials'));

        // Do we have global defaults to fall back on?
        $login = $apiKey = false;
        Event::handle('BitlyDefaultCredentials', array(&$login, &$apiKey));
        $haveGlobalDefaults = ($login && $apiKey);
        if ($login && $apiKey) {
            $this->out->element('p', 'form_guide',
                // TRANS: Form guide in administration panel for bit.ly URL shortening.
                _m('Leave these empty to use global default credentials.'));
        } else {
            $this->out->element('p', 'form_guide',
                // TRANS: Form guide in administration panel for bit.ly URL shortening.
                _m('If you leave these empty, bit.ly will be unavailable to users.'));
        }
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input(
            'default_login',
            // TRANS: Field label in administration panel for bit.ly URL shortening.
            _m('Login name'),
            null,
            'bitly'
        );
        $this->unli();

        $this->li();
        $this->input(
            'default_apikey',
            // TRANS: Field label in administration panel for bit.ly URL shortening.
             _m('API key'),
            null,
            'bitly'
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
        $this->out->submit('submit',
                           // TRANS: Button text to save setting in administration panel for bit.ly URL shortening.
                           _m('BUTTON','Save'),
                           'submit',
                           null,
                           // TRANS: Button title to save setting in administration panel for bit.ly URL shortening.
                           _m('Save bit.ly settings'));
    }
}

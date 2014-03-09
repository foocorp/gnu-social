<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Site administration panel
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer site settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SiteadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title for site administration panel.
        return _m('TITLE','Site');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for site administration panel.
        return _('Basic settings for this StatusNet site');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new SiteAdminPanelForm($this);
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
            'site' => array(
                'name',
                'broughtby',
                'broughtbyurl',
                'email',
                'timezone',
                'language',
                'site',
                'textlimit',
                'dupelimit',
                'logo',
                'ssllogo'
            )
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

    function validate(&$values)
    {
        // Validate site name

        if (empty($values['site']['name'])) {
            // TRANS: Client error displayed trying to save an empty site name.
            $this->clientError(_('Site name must have non-zero length.'));
        }

        // Validate email

        $values['site']['email'] = common_canonical_email($values['site']['email']);

        if (empty($values['site']['email'])) {
            // TRANS: Client error displayed trying to save site settings without a contact address.
            $this->clientError(_('You must have a valid contact email address.'));
        }
        if (!Validate::email($values['site']['email'], common_config('email', 'check_domain'))) {
            // TRANS: Client error displayed trying to save site settings without a valid contact address.
            $this->clientError(_('Not a valid email address.'));
        }

        // Validate logos
        if (!empty($values['site']['logo']) &&
                !common_valid_http_url($values['site']['logo'])) {
            // TRANS: Client error displayed when a logo URL is not valid.
            $this->clientError(_('Invalid logo URL.'));
        }

        if (!empty($values['site']['ssllogo']) &&
                !common_valid_http_url($values['site']['ssllogo'], true)) {
            // TRANS: Client error displayed when a SSL logo URL is invalid.
            $this->clientError(_('Invalid SSL logo URL.'));
        }

        // Validate timezone

        if (is_null($values['site']['timezone']) ||
            !in_array($values['site']['timezone'], DateTimeZone::listIdentifiers())) {
            // TRANS: Client error displayed trying to save site settings without a timezone.
            $this->clientError(_('Timezone not selected.'));
        }

        // Validate language

        if (!is_null($values['site']['language']) &&
            !in_array($values['site']['language'], array_keys(get_nice_language_list()))) {
            // TRANS: Client error displayed trying to save site settings with an invalid language code.
            // TRANS: %s is the invalid language code.
            $this->clientError(sprintf(_('Unknown language "%s".'), $values['site']['language']));
        }

        // Validate text limit

        if (!Validate::number($values['site']['textlimit'], array('min' => 0))) {
            // TRANS: Client error displayed trying to save site settings with a text limit below 0.
            $this->clientError(_('Minimum text limit is 0 (unlimited).'));
        }

        // Validate dupe limit

        if (!Validate::number($values['site']['dupelimit'], array('min' => 1))) {
            // TRANS: Client error displayed trying to save site settings with a text limit below 1.
            $this->clientError(_('Dupe limit must be one or more seconds.'));
        }
    }
}

// @todo FIXME: Class documentation missing.
class SiteAdminPanelForm extends AdminForm
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
        return common_local_url('siteadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'settings_admin_general'));
        // TRANS: Fieldset legend on site settings panel.
        $this->out->element('legend', null, _m('LEGEND','General'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        // TRANS: Field label on site settings panel.
        $this->input('name', _m('LABEL','Site name'),
                     // TRANS: Field title on site settings panel.
                     _('The name of your site, like "Yourcompany Microblog".'));
        $this->unli();

        $this->li();
        // TRANS: Field label on site settings panel.
        $this->input('broughtby', _('Brought by'),
                     // TRANS: Field title on site settings panel.
                     _('Text used for credits link in footer of each page.'));
        $this->unli();

        $this->li();
        // TRANS: Field label on site settings panel.
        $this->input('broughtbyurl', _('Brought by URL'),
                     // TRANS: Field title on site settings panel.
                     _('URL used for credits link in footer of each page.'));
        $this->unli();
        $this->li();
        // TRANS: Field label on site settings panel.
        $this->input('email', _('Email'),
                     // TRANS: Field title on site settings panel.
                     _('Contact email address for your site.'));
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->showLogo();

        $this->out->elementStart('fieldset', array('id' => 'settings_admin_local'));
        // TRANS: Fieldset legend on site settings panel.
        $this->out->element('legend', null, _m('LEGEND','Local'));
        $this->out->elementStart('ul', 'form_data');
        $timezones = array();

        foreach (DateTimeZone::listIdentifiers() as $k => $v) {
            $timezones[$v] = $v;
        }

        asort($timezones);

        $this->li();
        // TRANS: Dropdown label on site settings panel.
        $this->out->dropdown('timezone', _('Default timezone'),
                             // TRANS: Dropdown title on site settings panel.
                             $timezones, _('Default timezone for the site; usually UTC.'),
                             true, $this->value('timezone'));
        $this->unli();

        $this->li();
        $this->out->dropdown('language',
                             // TRANS: Dropdown label on site settings panel.
                             _('Default language'),
                             get_nice_language_list(),
                             // TRANS: Dropdown title on site settings panel.
                             _('The site language when autodetection from browser settings is not available.'),
                             false, $this->value('language'));
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' => 'settings_admin_limits'));
        // TRANS: Fieldset legend on site settings panel.
        $this->out->element('legend', null, _m('LEGEND','Limits'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $this->input('textlimit',
                     // TRANS: Field label on site settings panel.
                     _('Text limit'),
                     // TRANS: Field title on site settings panel.
                     _('Maximum number of characters for notices.'));
        $this->unli();

        $this->li();
        $this->input('dupelimit',
                     // TRANS: Field label on site settings panel.
                     _('Dupe limit'),
                     // TRANS: Field title on site settings panel.
                     _('How long users must wait (in seconds) to post the same thing again.'));
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    function showLogo()
    {
        $this->out->elementStart('fieldset', array('id' => 'settings_site_logo'));
        // TRANS: Fieldset legend for form to change logo.
        $this->out->element('legend', null, _('Logo'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('logo',
                     // TRANS: Field label for StatusNet site logo.
                     _('Site logo'),
                     // TRANS: Title for field label for StatusNet site logo.
                     'Logo for the site (full URL).');
        $this->unli();

        $this->li();
        $this->input('ssllogo',
                     // TRANS: Field label for SSL StatusNet site logo.
                     _('SSL logo'),
                     // TRANS: Title for field label for SSL StatusNet site logo.
                     'Logo to show on SSL pages (full URL).');
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
                           // TRANS: Button text for saving site settings.
                           _m('BUTTON','Save'),
                           'submit',
                           null,
                           // TRANS: Button title for saving site settings.
                           _('Save the site settings.'));
    }
}

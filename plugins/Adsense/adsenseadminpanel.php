<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Adsense administration panel
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
 * @category  Adsense
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
 * Administer adsense settings
 *
 * @category Adsense
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AdsenseadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        return _m('TITLE', 'AdSense');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        return _m('AdSense settings for this StatusNet site');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new AdsenseAdminPanelForm($this);
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
        static $settings = array('adsense' => array('adScript', 'client', 'mediumRectangle', 'rectangle', 'leaderboard', 'wideSkyscraper'));

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
 * Form for the adsense admin panel
 */
class AdsenseAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_adsense_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_adsense';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('adsenseadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'adsense_admin'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $this->input('client',
                     _m('Client ID'),
                     _m('Google client ID'),
                     'adsense');
        $this->unli();
        $this->li();
        $this->input('adScript',
                     _m('Ad script URL'),
                     _m('Script URL (advanced)'),
                     'adsense');
        $this->unli();
        $this->li();
        $this->input('mediumRectangle',
                     _m('Medium rectangle'),
                     _m('Medium rectangle slot code'),
                     'adsense');
        $this->unli();
        $this->li();
        $this->input('rectangle',
                     _m('Rectangle'),
                     _m('Rectangle slot code'),
                     'adsense');
        $this->unli();
        $this->li();
        $this->input('leaderboard',
                     _m('Leaderboard'),
                     _m('Leaderboard slot code'),
                     'adsense');
        $this->unli();
        $this->li();
        $this->input('wideSkyscraper',
                     _m('Skyscraper'),
                     _m('Wide skyscraper slot code'),
                     'adsense');
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
        $this->out->submit('submit', _m('Save'), 'submit', null, _m('Save AdSense settings'));
    }
}

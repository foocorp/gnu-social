<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Snapshots administration panel
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
 * Manage snapshots
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SnapshotadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title for admin panel to configure snapshots.
        return _m('TITLE','Snapshots');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for admin panel to configure snapshots.
        return _('Manage snapshot configuration');
    }

    /**
     * Show the snapshots admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new SnapshotAdminPanelForm($this);
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
            'snapshot' => array('run', 'reporturl', 'frequency')
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
        // Validate snapshot run value

        if (!in_array($values['snapshot']['run'], array('web', 'cron', 'never'))) {
            // TRANS: Client error displayed on admin panel for snapshots when providing an invalid run value.
            $this->clientError(_('Invalid snapshot run value.'));
        }

        // Validate snapshot frequency value

        if (!Validate::number($values['snapshot']['frequency'])) {
            // TRANS: Client error displayed on admin panel for snapshots when providing an invalid value for frequency.
            $this->clientError(_('Snapshot frequency must be a number.'));
        }

        // Validate report URL

        if (!is_null($values['snapshot']['reporturl'])
                && !common_valid_http_url($values['snapshot']['reporturl'])) {
            // TRANS: Client error displayed on admin panel for snapshots when providing an invalid report URL.
            $this->clientError(_('Invalid snapshot report URL.'));
        }
    }
}

// @todo FIXME: add documentation
class SnapshotAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_snapshot_admin_panel';
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
        return common_local_url('snapshotadminpanel');
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
            array('id' => 'settings_admin_snapshots')
        );
        // TRANS: Fieldset legend on admin panel for snapshots.
        $this->out->element('legend', null, _m('LEGEND','Snapshots'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $snapshot = array(
            // TRANS: Option in dropdown for snapshot method in admin panel for snapshots.
            'web' => _('Randomly during web hit'),
            // TRANS: Option in dropdown for snapshot method in admin panel for snapshots.
            'cron'  => _('In a scheduled job'),
            // TRANS: Option in dropdown for snapshot method in admin panel for snapshots.
            'never' => _('Never')
        );
        $this->out->dropdown(
            'run',
            // TRANS: Dropdown label for snapshot method in admin panel for snapshots.
            _('Data snapshots'),
            $snapshot,
            // TRANS: Dropdown title for snapshot method in admin panel for snapshots.
            _('When to send statistical data to status.net servers.'),
            false,
            $this->value('run', 'snapshot')
        );
        $this->unli();

        $this->li();
        $this->input(
            'frequency',
            // TRANS: Input field label for snapshot frequency in admin panel for snapshots.
            _('Frequency'),
            // TRANS: Input field title for snapshot frequency in admin panel for snapshots.
            _('Snapshots will be sent once every N web hits.'),
            'snapshot'
        );
        $this->unli();

        $this->li();
        $this->input(
            'reporturl',
            // TRANS: Input field label for snapshot report URL in admin panel for snapshots.
            _('Report URL'),
            // TRANS: Input field title for snapshot report URL in admin panel for snapshots.
            _('Snapshots will be sent to this URL.'),
            'snapshot'
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
            'submit',
            // TRANS: Button text to save snapshot settings.
            _m('BUTTON','Save'),
            'submit',
            null,
            // TRANS: Button title to save snapshot settings.
            _('Save snapshot settings.')
        );
    }
}

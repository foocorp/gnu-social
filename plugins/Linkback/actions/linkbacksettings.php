<?php
/**
 * Settings for Linkback
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
 * @author    Stephen Paul Weber <singpolyma@singpolyma.net>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Settings for Linkback
 *
 * Lets users opt out of sending linkbacks
 *
 * @category Settings
 * @author   Stephen Paul Weber <singpolyma@singpolyma.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */
class LinkbacksettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */
    function title()
    {
        // TRANS: Title of Linkback settings page for a user.
        return _m('TITLE','Linkback settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */
    function getInstructions()
    {
        // TRANS: Form instructions for Linkback settings.
        return _m('Linkbacks inform post authors when you link to them. ' .
                 'You can disable this feature here.');
    }

    function showContent()
    {
        $this->elementStart('form', array('method' => 'post',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('linkbacksettings')));
        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset');
        $this->element('legend', null, _m('LEGEND','Preferences'));
        $this->checkbox('disable_linkbacks', "Opt out of sending linkbacks for URLs you post", $this->scoped->getPref("linkbackplugin", "disable_linkbacks"));
        // TRANS: Button text to save OpenID prefs
        $this->submit('settings_linkback_prefs_save', _m('BUTTON','Save'), 'submit', 'save_prefs');
        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Handle a POST request
     *
     * @return void
     */
    protected function doPost()
    {
        $x = $this->scoped->setPref("linkbackplugin", "disable_linkbacks", $this->boolean('disable_linkbacks'));

        return _m('Linkback preferences saved.');
    }
}

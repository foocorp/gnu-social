<?php
/**
 * Form to set your personal poll settings
 *
 * StatusNet, the distributed open-source microblogging tool
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
 * @category  Plugins
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class PollSettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */
    function title()
    {
        // TRANS: Page title.
        return _m('Poll settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */

    function getInstructions()
    {
        // TRANS: Page instructions.
        return _m('Set your poll preferences');
    }

    protected function getForm()
    {
        $prefs = User_poll_prefs::getKV('user_id', $this->scoped->getID());
        $form = new PollPrefsForm($this, $prefs);
        return $form;
    }

    protected function doPost()
    {
        $upp = User_poll_prefs::getKV('user_id', $this->scoped->getID());
        $orig = null;

        if ($upp instanceof User_poll_prefs) {
            $orig = clone($upp);
        } else {
            $upp = new User_poll_prefs();
            $upp->user_id = $this->scoped->getID();
            $upp->created = common_sql_now();
        }

        $upp->hide_responses = $this->boolean('hide_responses');
        $upp->modified       = common_sql_now();

        if ($orig instanceof User_poll_prefs) {
            $upp->update($orig);
        } else {
            $upp->insert();
        }

        // TRANS: Confirmation shown when user profile settings are saved.
        return _('Settings saved.');
    }
}

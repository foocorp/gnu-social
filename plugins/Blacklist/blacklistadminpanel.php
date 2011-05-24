<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Blacklist administration panel
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer blacklist
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */
class BlacklistadminpanelAction extends AdminPanelAction
{
    /**
     * title of the admin panel
     *
     * @return string title
     */
    function title()
    {
        // TRANS: Title of blacklist plugin administration panel.
        return _m('TITLE','Blacklist');
    }

    /**
     * Panel instructions
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for blacklist plugin administration panel.
        return _m('Blacklisted URLs and nicknames');
    }

    /**
     * Show the actual form
     *
     * @return void
     *
     * @see BlacklistAdminPanelForm
     */
    function showForm()
    {
        $form = new BlacklistAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save the form settings
     *
     * @return void
     */
    function saveSettings()
    {
        $nickPatterns = $this->splitPatterns($this->trimmed('blacklist-nicknames'));
        Nickname_blacklist::saveNew($nickPatterns);

        $urlPatterns = $this->splitPatterns($this->trimmed('blacklist-urls'));
        Homepage_blacklist::saveNew($urlPatterns);

        return;
    }

    protected function splitPatterns($text)
    {
        $patterns = array();
        foreach (explode("\n", $text) as $raw) {
            $trimmed = trim($raw);
            if ($trimmed != '') {
                $patterns[] = $trimmed;
            }
        }
        return $patterns;
    }

    /**
     * Validate the values
     *
     * @param array &$values 2d array of values to check
     *
     * @return boolean success flag
     */
    function validate(&$values)
    {
        return true;
    }
}

/**
 * Admin panel form for blacklist panel
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */
class BlacklistAdminPanelForm extends Form
{
    /**
     * ID of the form
     *
     * @return string ID
     */
    function id()
    {
        return 'blacklistadminpanel';
    }

    /**
     * Class of the form
     *
     * @return string class
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action we post to
     *
     * @return string action URL
     */
    function action()
    {
        return common_local_url('blacklistadminpanel');
    }

    /**
     * Show the form controls
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li');

        $nickPatterns = Nickname_blacklist::getPatterns();

        // TRANS: Field label in blacklist plugin administration panel.
        $this->out->textarea('blacklist-nicknames', _m('Nicknames'),
                             implode("\r\n", $nickPatterns),
                             // TRANS: Field title in blacklist plugin administration panel.
                             _m('Patterns of nicknames to block, one per line.'));
        $this->out->elementEnd('li');

        $urlPatterns = Homepage_blacklist::getPatterns();

        $this->out->elementStart('li');
        // TRANS: Field label in blacklist plugin administration panel.
        $this->out->textarea('blacklist-urls', _m('URLs'),
                             implode("\r\n", $urlPatterns),
                             // TRANS: Field title in blacklist plugin administration panel.
                             _m('Patterns of URLs to block, one per line.'));
        $this->out->elementEnd('li');

        $this->out->elementEnd('ul');
    }

    /**
     * Buttons for submitting
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Button text in blacklist plugin administration panel to save settings.
                           _m('BUTTON','Save'),
                           'submit',
                           null,
                           // TRANS: Button title in blacklist plugin administration panel to save settings.
                           _m('Save site settings.'));
    }
}

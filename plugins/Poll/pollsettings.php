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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

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

    /**
     * Show the form for Poll
     *
     * @return void
     */
    function showContent()
    {
        $user = common_current_user();

        $prefs = User_poll_prefs::getKV('user_id', $user->id);

        $form = new PollPrefsForm($this, $prefs);

        $form->show();
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handlePost()
    {
        $user = common_current_user();

        $upp = User_poll_prefs::getKV('user_id', $user->id);
        $orig = null;

        if (!empty($upp)) {
            $orig = clone($upp);
        } else {
            $upp = new User_poll_prefs();
            $upp->user_id = $user->id;
            $upp->created = common_sql_now();
        }

        $upp->hide_responses = $this->boolean('hide_responses');
        $upp->modified       = common_sql_now();

        if (!empty($orig)) {
            $upp->update($orig);
        } else {
            $upp->insert();
        }

        // TRANS: Confirmation shown when user profile settings are saved.
        $this->showForm(_('Settings saved.'), true);

        return;
    }
}

class PollPrefsForm extends Form
{
    var $prefs;

    function __construct($out, $prefs)
    {
        parent::__construct($out);
        $this->prefs = $prefs;
    }

    /**
     * Visible or invisible data elements
     *
     * Display the form fields that make up the data of the form.
     * Sub-classes should overload this to show their data.
     *
     * @return void
     */

    function formData()
    {
        $this->elementStart('fieldset');
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('hide_responses',
                        _('Do not deliver poll responses to my home timeline'),
                        (!empty($this->prefs) && $this->prefs->hide_responses));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
    }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     *
     * @return void
     */

    function formActions()
    {
        $this->submit('submit', _('Save'));
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_poll_prefs';
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     *
     * @return string URL to post to
     */

    function action()
    {
        return common_local_url('pollsettings');
    }

    /**
     * Class of the form. May include space-separated list of multiple classes.
     *
     * @return string the form's class
     */

    function formClass()
    {
        return 'form_settings';
    }
}

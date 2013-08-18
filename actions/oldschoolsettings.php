<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Settings panel for old-school UI
 * 
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  Oldschool
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Old-school settings
 *
 * @category  Oldschool
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class OldschoolsettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Page title for profile settings.
        return _('Old school UI settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Usage instructions for profile settings.
        return _('If you like it "the old way", you can set that here.');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        if (!common_config('oldschool', 'enabled')) {
            throw new ClientException("Old-school settings not enabled.");
        }
        parent::prepare($argarray);
        return true;
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

        $osp = Old_school_prefs::getKV('user_id', $user->id);
        $orig = null;

        if (!empty($osp)) {
            $orig = clone($osp);
        } else {
            $osp = new Old_school_prefs();
            $osp->user_id = $user->id;
            $osp->created = common_sql_now();
        }

        $osp->stream_mode_only  = $this->boolean('stream_mode_only');
        $osp->conversation_tree = $this->boolean('conversation_tree');
        $osp->stream_nicknames  = $this->boolean('stream_nicknames');
        $osp->modified          = common_sql_now();

        if (!empty($orig)) {
            $osp->update($orig);
        } else {
            $osp->insert();
        }

        // TRANS: Confirmation shown when user profile settings are saved.
        $this->showForm(_('Settings saved.'), true);

        return;
    }

    function showContent()
    {
        $user = common_current_user();
        $form = new OldSchoolForm($this, $user);
        $form->show();
    }
}

class OldSchoolForm extends Form
{
    var $user;

    function __construct($out, $user)
    {
        parent::__construct($out);
        $this->user = $user;
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
        $this->checkbox('stream_mode_only', _('Only stream mode (no conversations) in timelines'),
                        $this->user->streamModeOnly());
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('conversation_tree', _('Show conversation page as hierarchical trees'),
                        $this->user->conversationTree());
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('stream_nicknames', _('Show nicknames (not full names) in timelines'),
                        $this->user->streamNicknames());
        $this->elementEnd('li');
        $this->elementEnd('fieldset');
        $this->elementEnd('ul');
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
        return 'form_oldschool';
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
        return common_local_url('oldschoolsettings');
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

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for admin panel actions
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
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * superclass for admin panel actions
 *
 * Common code for all admin panel actions.
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @todo Find some commonalities with SettingsAction and combine
 */
class AdminPanelAction extends Action
{
    var $success = true;
    var $msg     = null;

    /**
     * Prepare for the action
     *
     * We check to see that the user is logged in, has
     * authenticated in this session, and has the right
     * to configure the site.
     *
     * @param array $args Array of arguments from Web driver
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        // User must be logged in.

        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
        }

        $user = common_current_user();

        // ...because they're logged in

        assert(!empty($user));

        // It must be a "real" login, not saved cookie login

        if (!common_is_real_login()) {
            // Cookie theft is too easy; we require automatic
            // logins to re-authenticate before admining the site
            common_set_returnto($this->selfUrl());
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        }

        // User must have the right to change admin settings

        if (!$user->hasRight(Right::CONFIGURESITE)) {
            // TRANS: Client error message thrown when a user tries to change admin settings but has no access rights.
            $this->clientError(_('You cannot make changes to this site.'));
        }

        // This panel must be enabled

        $name = $this->trimmed('action');

        $name = mb_substr($name, 0, -10);

        if (!self::canAdmin($name)) {
            // TRANS: Client error message throw when a certain panel's settings cannot be changed.
            $this->clientError(_('Changes to that panel are not allowed.'), 403);
        }

        return true;
    }

    /**
     * handle the action
     *
     * Check session token and try to save the settings if this is a
     * POST. Otherwise, show the form.
     *
     * @param array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->checkSessionToken();
            try {
                $this->saveSettings();

                // Reload settings

                Config::loadSettings();

                $this->success = true;
                // TRANS: Message after successful saving of administrative settings.
                $this->msg     = _('Settings saved.');
            } catch (Exception $e) {
                $this->success = false;
                $this->msg     = $e->getMessage();
            }
        }
        $this->showPage();
    }

    /**
     * Show tabset for this page
     *
     * Uses the AdminPanelNav widget
     *
     * @return void
     * @see AdminPanelNav
     */
    function showLocalNav()
    {
        $nav = new AdminPanelNav($this);
        $nav->show();
    }

    /**
     * Show the content section of the page
     *
     * Here, we show the admin panel's form.
     *
     * @return void.
     */
    function showContent()
    {
        $this->showForm();
    }

    /**
     * Show content block. Overrided just to add a special class
     * to the content div to allow styling.
     *
     * @return nothing
     */
    function showContentBlock()
    {
        $this->elementStart('div', array('id' => 'content', 'class' => 'admin'));
        $this->showPageTitle();
        $this->showPageNoticeBlock();
        $this->elementStart('div', array('id' => 'content_inner'));
        // show the actual content (forms, lists, whatever)
        $this->showContent();
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * show human-readable instructions for the page, or
     * a success/failure on save.
     *
     * @return void
     */
    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('div', ($this->success) ? 'success' : 'error',
                           $this->msg);
        } else {
            $inst   = $this->getInstructions();
            $output = common_markup_to_html($inst);

            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    /**
     * Show the admin panel form
     *
     * Sub-classes should overload this.
     *
     * @return void
     */
    function showForm()
    {
        // TRANS: Client error message.
        $this->clientError(_('showForm() not implemented.'));
    }

    /**
     * Instructions for using this form.
     *
     * String with instructions for using the form.
     *
     * Subclasses should overload this.
     *
     * @return void
     */
    function getInstructions()
    {
        return '';
    }

    /**
     * Save settings from the form
     *
     * Validate and save the settings from the user.
     *
     * @return void
     */
    function saveSettings()
    {
        // TRANS: Client error message
        $this->clientError(_('saveSettings() not implemented.'));
    }

    function canAdmin($name)
    {
        $isOK = false;

        if (Event::handle('AdminPanelCheck', array($name, &$isOK))) {
            $isOK = in_array($name, common_config('admin', 'panels'));
        }

        return $isOK;
    }

    function showProfileBlock()
    {
    }
}

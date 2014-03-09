<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action class to delete an OAuth application
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
 * @category  Action
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Delete an OAuth appliction
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class DeleteapplicationAction extends Action
{
    var $app = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to delete an application while not logged in.
            $this->clientError(_('You must be logged in to delete an application.'));
        }

        $id        = (int)$this->arg('id');
        $this->app = Oauth_application::getKV('id', $id);

        if (empty($this->app)) {
            // TRANS: Client error displayed trying to delete an application that does not exist.
            $this->clientError(_('Application not found.'));
        }

        $cur = common_current_user();

        if ($cur->id != $this->app->owner) {
            // TRANS: Client error displayed trying to delete an application the current user does not own.
            $this->clientError(_('You are not the owner of this application.'), 401);
        }

        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                // TRANS: Client error displayed when the session token does not match or is not given.
                $this->clientError(_('There was a problem with your session token.'));
            }

            if ($this->arg('no')) {
                common_redirect(common_local_url('showapplication',
                                                 array('id' => $this->app->id)), 303);
            } elseif ($this->arg('yes')) {
                $this->handlePost();
                common_redirect(common_local_url('oauthappssettings'), 303);
            } else {
                $this->showPage();
            }
        }
    }

    function showContent() {
        $this->areYouSureForm();
    }

    function title() {
        // TRANS: Title for delete application page.
        return _('Delete application');
    }

    function showNoticeForm() {
        // nop
    }

    /**
     * Confirm with user.
     *
     * Shows a confirmation form.
     *
     * @return void
     */
    function areYouSureForm()
    {
        $id = $this->app->id;
        $this->elementStart('form', array('id' => 'deleteapplication-' . $id,
                                          'method' => 'post',
                                          'class' => 'form_settings form_entity_block',
                                          'action' => common_local_url('deleteapplication',
                                                                       array('id' => $this->app->id))));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        // TRANS: Fieldset legend on delete application page.
        $this->element('legend', _('Delete application'));
        $this->element('p', null,
                       // TRANS: Confirmation text on delete application page.
                       _('Are you sure you want to delete this application? '.
                         'This will clear all data about the application from the '.
                         'database, including all existing user connections.'));
        $this->submit('form_action-no',
                      // TRANS: Button label on the delete application form.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when deleting an application.
                      _('Do not delete this application.'));
        $this->submit('form_action-yes',
                      // TRANS: Button label on the delete application form.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      // TRANS: Submit button title for 'Yes' when deleting an application.
                      'yes', _('Delete this application.'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Actually delete the app
     *
     * @return void
     */
    function handlePost()
    {
        $this->app->delete();
    }
}

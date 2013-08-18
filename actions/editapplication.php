<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Edit an OAuth Application
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
 * @category  Applications
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Edit the details of an OAuth application
 *
 * This is the form for editing an application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class EditApplicationAction extends Action
{
    var $msg   = null;
    var $owner = null;
    var $app   = null;

    function title()
    {
        // TRANS: Title for "Edit application" form.
        return _('Edit application');
    }

    /**
     * Prepare to run
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to edit an application while not logged in.
            $this->clientError(_('You must be logged in to edit an application.'));
            return false;
        }

        $id = (int)$this->arg('id');

        $this->app   = Oauth_application::getKV($id);
        $this->owner = User::getKV($this->app->owner);
        $cur         = common_current_user();

        if ($cur->id != $this->owner->id) {
            // TRANS: Client error displayed trying to edit an application while not being its owner.
            $this->clientError(_('You are not the owner of this application.'), 401);
        }

        if (!$this->app) {
            // TRANS: Client error displayed trying to edit an application that does not exist.
            $this->clientError(_('No such application.'));
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the app.
     *
     * @param array $args unused
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost($args);
        } else {
            $this->showForm();
        }
    }

    function handlePost($args)
    {
        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
            ) {
            // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
            // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
            $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                      intval($_SERVER['CONTENT_LENGTH']));
            $this->clientException(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token.'));
            return;
        }

        $cur = common_current_user();

        if ($this->arg('cancel')) {
            common_redirect(common_local_url('showapplication',
                                             array('id' => $this->app->id)), 303);
        } elseif ($this->arg('save')) {
            $this->trySave();
        } else {
            // TRANS: Client error displayed submitting invalid form data for edit application.
            $this->clientError(_('Unexpected form submission.'));
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showContent()
    {
        $form = new ApplicationEditForm($this, $this->app);
        $form->show();
    }

    function showPageNotice()
    {
        if (!empty($this->msg)) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           // TRANS: Instructions for "Edit application" form.
                           _('Use this form to edit your application.'));
        }
    }

    function trySave()
    {
        $name         = $this->trimmed('name');
        $description  = $this->trimmed('description');
        $source_url   = $this->trimmed('source_url');
        $organization = $this->trimmed('organization');
        $homepage     = $this->trimmed('homepage');
        $callback_url = $this->trimmed('callback_url');
        $type         = $this->arg('app_type');
        $access_type  = $this->arg('default_access_type');

        if (empty($name)) {
            // TRANS: Validation error shown when not providing a name in the "Edit application" form.
            $this->showForm(_('Name is required.'));
            return;
        } elseif (mb_strlen($name) > 255) {
            // TRANS: Validation error shown when providing too long a name in the "Edit application" form.
            $this->showForm(_('Name is too long (maximum 255 characters).'));
            return;
        } else if ($this->nameExists($name)) {
            // TRANS: Validation error shown when providing a name for an application that already exists in the "Edit application" form.
            $this->showForm(_('Name already in use. Try another one.'));
            return;
        } elseif (empty($description)) {
            // TRANS: Validation error shown when not providing a description in the "Edit application" form.
            $this->showForm(_('Description is required.'));
            return;
        } elseif (Oauth_application::descriptionTooLong($description)) {
            $this->showForm(sprintf(
                // TRANS: Validation error shown when providing too long a description in the "Edit application" form.
                // TRANS: %d is the maximum number of allowed characters.
                _m('Description is too long (maximum %d character).',
                  'Description is too long (maximum %d characters).',
                  Oauth_application::maxDesc()),
                                    Oauth_application::maxDesc()));
            return;
        } elseif (mb_strlen($source_url) > 255) {
            // TRANS: Validation error shown when providing too long a source URL in the "Edit application" form.
            $this->showForm(_('Source URL is too long.'));
            return;
        } elseif ((mb_strlen($source_url) > 0)
                  && !Validate::uri($source_url,
                                    array('allowed_schemes' => array('http', 'https'))))
            {
                // TRANS: Validation error shown when providing an invalid source URL in the "Edit application" form.
                $this->showForm(_('Source URL is not valid.'));
                return;
        } elseif (empty($organization)) {
            // TRANS: Validation error shown when not providing an organisation in the "Edit application" form.
            $this->showForm(_('Organization is required.'));
            return;
        } elseif (mb_strlen($organization) > 255) {
            // TRANS: Validation error shown when providing too long an arganisation name in the "Edit application" form.
            $this->showForm(_('Organization is too long (maximum 255 characters).'));
            return;
        } elseif (empty($homepage)) {
            // TRANS: Form validation error show when an organisation name has not been provided in the edit application form.
            $this->showForm(_('Organization homepage is required.'));
            return;
        } elseif ((mb_strlen($homepage) > 0)
                  && !Validate::uri($homepage,
                                    array('allowed_schemes' => array('http', 'https'))))
            {
                // TRANS: Validation error shown when providing an invalid homepage URL in the "Edit application" form.
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } elseif (mb_strlen($callback_url) > 255) {
                // TRANS: Validation error shown when providing too long a callback URL in the "Edit application" form.
                $this->showForm(_('Callback is too long.'));
                return;
            } elseif (mb_strlen($callback_url) > 0
                      && !Validate::uri($source_url,
                                        array('allowed_schemes' => array('http', 'https'))
                                        ))
                {
                    // TRANS: Validation error shown when providing an invalid callback URL in the "Edit application" form.
                    $this->showForm(_('Callback URL is not valid.'));
                    return;
                }

        $cur = common_current_user();

        // Checked in prepare() above

        assert(!is_null($cur));
        assert(!is_null($this->app));

        $orig = clone($this->app);

        $this->app->name         = $name;
        $this->app->description  = $description;
        $this->app->source_url   = $source_url;
        $this->app->organization = $organization;
        $this->app->homepage     = $homepage;
        $this->app->callback_url = $callback_url;
        $this->app->type         = $type;

        common_debug("access_type = $access_type");

        if ($access_type == 'r') {
            $this->app->access_type = 1;
        } else {
            $this->app->access_type = 3;
        }

        $result = $this->app->update($orig);

        // Note: 0 means no rows changed, which can happen if the only
        // thing we changed was the icon, since it's not altered until
        // the next step.
        if ($result === false) {
            common_log_db_error($this->app, 'UPDATE', __FILE__);
            // TRANS: Server error occuring when an application could not be updated from the "Edit application" form.
            $this->serverError(_('Could not update application.'));
        }

        $this->app->uploadLogo();

        common_redirect(common_local_url('oauthappssettings'), 303);
    }

    /**
     * Does the app name already exist?
     *
     * Checks the DB to see someone has already registered an app
     * with the same name.
     *
     * @param string $name app name to check
     *
     * @return boolean true if the name already exists
     */
    function nameExists($name)
    {
        $newapp = Oauth_application::getKV('name', $name);
        if (empty($newapp)) {
            return false;
        } else {
            return $newapp->id != $this->app->id;
        }
    }
}

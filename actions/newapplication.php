<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Register a new OAuth Application
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
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Add a new application
 *
 * This is the form for adding a new application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class NewApplicationAction extends OwnerDesignAction
{
    var $msg;

    function title()
    {
        // TRANS: This is the title of the form for adding a new application.
        return _('New application');
    }

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to add a new application while not logged in.
            $this->clientError(_('You must be logged in to register an application.'));
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
            $this->clientError(_('There was a problem with your session token.'));
            return;
        }

        $cur = common_current_user();

        if ($this->arg('cancel')) {
            common_redirect(common_local_url('oauthappssettings'), 303);
        } elseif ($this->arg('save')) {
            $this->trySave();
        } else {
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
        $form = new ApplicationEditForm($this);
        $form->show();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           _('Use this form to register a new application.'));
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
             $this->showForm(_('Name is required.'));
             return;
        } else if ($this->nameExists($name)) {
            $this->showForm(_('Name already in use. Try another one.'));
            return;
        } elseif (mb_strlen($name) > 255) {
            $this->showForm(_('Name is too long (maximum 255 characters).'));
            return;
        } elseif (empty($description)) {
            $this->showForm(_('Description is required.'));
            return;
        } elseif (Oauth_application::descriptionTooLong($description)) {
            $this->showForm(sprintf(
                // TRANS: Form validation error in New application form.
                // TRANS: %d is the maximum number of characters for the description.
                _m('Description is too long (maximum %d character).',
                   'Description is too long (maximum %d characters).',
                   Oauth_application::maxDesc()),
                Oauth_application::maxDesc()));
            return;
        } elseif (empty($source_url)) {
            $this->showForm(_('Source URL is required.'));
            return;
        } elseif ((strlen($source_url) > 0)
            && !Validate::uri(
                $source_url,
                array('allowed_schemes' => array('http', 'https'))
                )
            )
        {
            $this->showForm(_('Source URL is not valid.'));
            return;
        } elseif (empty($organization)) {
            $this->showForm(_('Organization is required.'));
            return;
        } elseif (mb_strlen($organization) > 255) {
            $this->showForm(_('Organization is too long (maximum 255 characters).'));
            return;
        } elseif (empty($homepage)) {
            $this->showForm(_('Organization homepage is required.'));
            return;
        } elseif ((strlen($homepage) > 0)
            && !Validate::uri(
                $homepage,
                array('allowed_schemes' => array('http', 'https'))
                )
            )
        {
            $this->showForm(_('Homepage is not a valid URL.'));
            return;
        } elseif (mb_strlen($callback_url) > 255) {
            $this->showForm(_('Callback is too long.'));
            return;
        } elseif (strlen($callback_url) > 0
            && !Validate::uri(
                $source_url,
                array('allowed_schemes' => array('http', 'https'))
                )
            )
        {
            $this->showForm(_('Callback URL is not valid.'));
            return;
        }

        $cur = common_current_user();

        // Checked in prepare() above

        assert(!is_null($cur));

        $app = new Oauth_application();

        $app->query('BEGIN');

        $app->name         = $name;
        $app->owner        = $cur->id;
        $app->description  = $description;
        $app->source_url   = $source_url;
        $app->organization = $organization;
        $app->homepage     = $homepage;
        $app->callback_url = $callback_url;
        $app->type         = $type;

        // Yeah, I dunno why I chose bit flags. I guess so I could
        // copy this value directly to Oauth_application_user
        // access_type which I think does need bit flags -- Z

        if ($access_type == 'r') {
            $app->setAccessFlags(true, false);
        } else {
            $app->setAccessFlags(true, true);
        }

        $app->created = common_sql_now();

        // generate consumer key and secret

        $consumer = Consumer::generateNew();

        $result = $consumer->insert();

        if (!$result) {
            common_log_db_error($consumer, 'INSERT', __FILE__);
            $this->serverError(_('Could not create application.'));
        }

        $app->consumer_key = $consumer->consumer_key;

        $this->app_id = $app->insert();

        if (!$this->app_id) {
            common_log_db_error($app, 'INSERT', __FILE__);
            $this->serverError(_('Could not create application.'));
            $app->query('ROLLBACK');
        }

        $app->uploadLogo();

        $app->query('COMMIT');

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
        $app = Oauth_application::staticGet('name', $name);
        return !empty($app);
    }

}


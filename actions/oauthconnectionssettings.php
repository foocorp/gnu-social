<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List a user's OAuth connected applications
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
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/applicationlist.php';
require_once INSTALLDIR . '/lib/statusnetoauthstore.php';

/**
 * Show connected OAuth applications
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */
class OauthconnectionssettingsAction extends SettingsAction
{
    var $page        = null;
    var $oauth_token = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->oauth_token = $this->arg('oauth_token');
        $this->page = ($this->arg('page')) ? ($this->arg('page') + 0) : 1;
        return true;
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for OAuth connection settings.
        return _('Connected applications');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instructions for OAuth connection settings.
        return _('The following connections exist for your account.');
    }

    /**
     * Content area of the page
     *
     * @return void
     */

    function showContent()
    {
        $user    = common_current_user();
        $profile = $user->getProfile();

        $offset = ($this->page - 1) * APPS_PER_PAGE;
        $limit  =  APPS_PER_PAGE + 1;

        $connection = $user->getConnectedApps($offset, $limit);

        $cnt = 0;

        if (!empty($connection)) {
            $cal = new ConnectedAppsList($connection, $user, $this);
            $cnt = $cal->show();
        }

        if ($cnt == 0) {
            $this->showEmptyListMessage();
        }

        $this->pagination(
            $this->page > 1,
            $cnt > APPS_PER_PAGE,
            $this->page,
            'connectionssettings',
            array('nickname' => $user->nickname)
        );
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
     *
     * @return void
     */
    function handlePost()
    {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('revoke')) {
            $this->revokeAccess($this->oauth_token);
        } else {
            // TRANS: Client error when submitting a form with unexpected information.
            $this->clientError(_('Unexpected form submission.'), 401);
            return false;
        }
    }

    /**
     * Revoke an access token
     *
     * XXX: Confirm revoke before doing it
     *
     * @param int $appId the ID of the application
     *
     */
    function revokeAccess($token)
    {
        $cur = common_current_user();

        $appUser = Oauth_application_user::getByUserAndToken($cur, $token);

        if (empty($appUser)) {
            // TRANS: Client error when trying to revoke access for an application while not being a user of it.
            $this->clientError(_('You are not a user of that application.'), 401);
            return false;
        }

        $app = Oauth_application::getKV('id', $appUser->application_id);

        $datastore = new ApiStatusNetOAuthDataStore();
        $datastore->revoke_token($appUser->token, 1);

        $result = $appUser->delete();

        if (!$result) {
            common_log_db_error($orig, 'DELETE', __FILE__);
            // TRANS: Client error when revoking access has failed for some reason.
            // TRANS: %s is the application ID revoking access failed for.
            $this->clientError(sprintf(_('Unable to revoke access for application: %s.'), $app->id));
            return false;
        }

        $msg = 'API OAuth - user %s (id: %d) revoked access token %s for app id %d';
        common_log(
            LOG_INFO,
            sprintf(
                $msg,
                $cur->nickname,
                $cur->id,
                $appUser->token,
                $appUser->application_id
            )
        );

        $msg = sprintf(
            // TRANS: Success message after revoking access for an application.
            // TRANS: %1$s is the application name, %2$s is the first part of the user token.
            _('You have successfully revoked access for %1$s and the access token starting with %2$s.'),
             $app->name,
             substr($appUser->token, 0, 7)
        );

        $this->showForm($msg, true);
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message when no applications have been authorised yet.
        $message = _('You have not authorized any applications to use your account.');

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showSections()
    {
        $cur = common_current_user();

        $this->elementStart('div', array('id' => 'developer-help', 'class' => 'section'));

        $this->element('h2', null, 'Developers');
        $this->elementStart('p');

        $devMsg = sprintf(
            // TRANS: Note for developers in the OAuth connection settings form.
            // TRANS: This message contains a Markdown link. Do not separate "](".
            // TRANS: %s is the URL to the OAuth settings.
            _('Are you a developer? [Register an OAuth client application](%s) to use with this instance of StatusNet.'),
            common_local_url('oauthappssettings')
        );

        $output = common_markup_to_html($devMsg);

        $this->raw($output);
        $this->elementEnd('p');

        $this->elementEnd('section');
    }
}

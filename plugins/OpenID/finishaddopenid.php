<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Complete adding an OpenID
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
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

/**
 * Complete adding an OpenID
 *
 * Handle the return from an OpenID verification
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class FinishaddopenidAction extends Action
{
    var $msg = null;

    /**
     * Handle the redirect back from OpenID confirmation
     *
     * Check to see if the user's logged in, and then try
     * to use the OpenID login system.
     *
     * @param array $args $_REQUEST arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_m('Not logged in.'));
        } else {
            $this->tryLogin();
        }
    }

    /**
     * Try to log in using OpenID
     *
     * Check the OpenID for validity; potentially store it.
     *
     * @return void
     */
    function tryLogin()
    {
        $consumer = oid_consumer();

        $response = $consumer->complete(common_local_url('finishaddopenid'));

        if ($response->status == Auth_OpenID_CANCEL) {
            // TRANS: Status message in case the response from the OpenID provider is that the logon attempt was cancelled.
            $this->message(_m('OpenID authentication cancelled.'));
            return;
        } else if ($response->status == Auth_OpenID_FAILURE) {
            // TRANS: OpenID authentication failed; display the error message.
            // TRANS: %s is the error message.
            $this->message(sprintf(_m('OpenID authentication failed: %s.'),
                                   $response->message));
        } else if ($response->status == Auth_OpenID_SUCCESS) {

            $display   = $response->getDisplayIdentifier();
            $canonical = ($response->endpoint && $response->endpoint->canonicalID) ?
              $response->endpoint->canonicalID : $display;

            $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);

            if ($sreg_resp) {
                $sreg = $sreg_resp->contents();
            }

            // Launchpad teams extension
            if (!oid_check_teams($response)) {
                // TRANS: OpenID authentication error.
                $this->message(_m('OpenID authentication aborted: You are not allowed to login to this site.'));
                return;
            }

            $cur = common_current_user();

            $other = oid_get_user($canonical);

            if ($other) {
                if ($other->id == $cur->id) {
                    // TRANS: Message in case a user tries to add an OpenID that is already connected to them.
                    $this->message(_m('You already have this OpenID!'));
                } else {
                    // TRANS: Message in case a user tries to add an OpenID that is already used by another user.
                    $this->message(_m('Someone else already has this OpenID.'));
                }
                return;
            }

            // start a transaction

            $cur->query('BEGIN');

            $result = oid_link_user($cur->id, $canonical, $display);

            if (!$result) {
                // TRANS: Message in case the OpenID object cannot be connected to the user.
                $this->message(_m('Error connecting user.'));
                return;
            }
            if (Event::handle('StartOpenIDUpdateUser', array($cur, $canonical, &$sreg))) {
                if ($sreg) {
                    if (!oid_update_user($cur, $sreg)) {
                        // TRANS: Message in case the user or the user profile cannot be saved in StatusNet.
                        $this->message(_m('Error updating profile.'));
                        return;
                    }
                }
            }
            Event::handle('EndOpenIDUpdateUser', array($cur, $canonical, $sreg));

            // success!

            $cur->query('COMMIT');

            oid_set_last($display);

            common_redirect(common_local_url('openidsettings'), 303);
        }
    }

    /**
     * Show a failure message
     *
     * Something went wrong. Save the message, and show the page.
     *
     * @param string $msg Error message to show
     *
     * @return void
     */
    function message($msg)
    {
        $this->message = $msg;
        $this->showPage();
    }

    /**
     * Title of the page
     *
     * @return string title
     */
    function title()
    {
        // TRANS: Title after getting the status of the OpenID authorisation request.
        return _m('OpenID Login');
    }

    /**
     * Show error message
     *
     * @return void
     */
    function showPageNotice()
    {
        if ($this->message) {
            $this->element('p', 'error', $this->message);
        }
    }
}

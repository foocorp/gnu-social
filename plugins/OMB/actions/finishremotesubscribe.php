<?php
/**
 * Handler for remote subscription finish callback
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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
 **/

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once dirname(__FILE__) . '/../extlib/libomb/service_consumer.php';
require_once dirname(__FILE__) . '/../lib/omb.php';

/**
 * Handler for remote subscription finish callback
 *
 * When a remote user subscribes a local user, a redirect to this action is
 * issued after the remote user authorized their service to subscribe.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class FinishremotesubscribeAction extends Action
{
    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return nothing
     *
     */
    function handle($args)
    {
        parent::handle($args);

        /* Restore session data. RemotesubscribeAction should have stored
           this entry. */
        $service  = unserialize($_SESSION['oauth_authorization_request']);

        if (!$service) {
            // TRANS: Client error displayed when subscribing to a remote profile and an unexpected response is received.
            $this->clientError(_('Not expecting this response!'));
            return;
        }

        common_debug('stored request: '. print_r($service, true), __FILE__);

        /* Create user objects for both users. Do it early for request
           validation. */
        $user = User::getKV('uri', $service->getListeneeURI());

        if (!$user) {
            // TRANS: Client error displayed when subscribing to a remote profile that does not exist.
            $this->clientError(_('User being listened to does not exist.'));
            return;
        }

        $other = User::getKV('uri', $service->getListenerURI());

        if ($other) {
            // TRANS: Client error displayed when subscribing to a remote profile that is a local profile.
            $this->clientError(_('You can use the local subscription!'));
            return;
        }

        $remote = Remote_profile::getKV('uri', $service->getListenerURI());
        if ($remote) {
            // Note remote profile may not have been saved yet.
            // @fixme not convinced this is correct at all!

            $profile = Profile::getKV($remote->id);

            if ($user->hasBlocked($profile)) {
                // TRANS: Client error displayed when subscribing to a remote profile that is blocked form subscribing to.
                $this->clientError(_('That user has blocked you from subscribing.'));
                return;
            }
        }

        /* Perform the handling itself via libomb. */
        try {
            $service->finishAuthorization();
        } catch (OAuthException $e) {
            if ($e->getMessage() == 'The authorized token does not equal the ' .
                                    'submitted token.') {
                // TRANS: Client error displayed when subscribing to a remote profile without providing an authorised token.
                $this->clientError(_('You are not authorized.'));
                return;
            } else {
                // TRANS: Client error displayed when subscribing to a remote profile and conversion of the request token to access token fails.
                $this->clientError(_('Could not convert request token to ' .
                                     'access token.'));
                return;
            }
        } catch (OMB_RemoteServiceException $e) {
            // TRANS: Client error displayed when subscribing to a remote profile fails because of an unsupported version of the OMB protocol.
            $this->clientError(_('Remote service uses unknown version of ' .
                                 'OMB protocol.'));
            return;
        } catch (Exception $e) {
            common_debug('Got exception ' . print_r($e, true), __FILE__);
            $this->clientError($e->getMessage());
            return;
        }

        /* The service URLs are not accessible from datastore, so setting them
           after insertion of the profile. */
        $remote = Remote_profile::getKV('uri', $service->getListenerURI());
        $orig_remote = clone($remote);

        $remote->postnoticeurl    =
                            $service->getServiceURI(OMB_ENDPOINT_POSTNOTICE);
        $remote->updateprofileurl =
                            $service->getServiceURI(OMB_ENDPOINT_UPDATEPROFILE);

        if (!$remote->update($orig_remote)) {
                // TRANS: Server error displayed when subscribing to a remote profile fails because the remote profile could not be updated.
                $this->serverError(_('Error updating remote profile.'));
                return;
        }

        /* Clear the session data. */
        unset($_SESSION['oauth_authorization_request']);

        /* If we show subscriptions in reverse chronological order, the new one
           should show up close to the top of the page. */
        common_redirect(common_local_url('subscribers', array('nickname' =>
                                                             $user->nickname)),
                        303);
    }
}

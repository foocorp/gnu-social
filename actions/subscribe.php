<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
 *
 * Subscription action.
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
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Subscription action
 *
 * Subscribing to a profile. Does not work for OMB 0.1 remote subscriptions,
 * but may work for other remote subscription protocols, like OStatus.
 *
 * Takes parameters:
 *
 *    - subscribeto: a profile ID
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class SubscribeAction extends Action
{
    var $user;
    var $other;

    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        // Only allow POST requests

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            // TRANS: Client error displayed trying to perform any request method other than POST.
            // TRANS: Do not translate POST.
            $this->clientError(_('This action only accepts POST requests.'));
            return false;
        }

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token is not okay.
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
            return false;
        }

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return false;
        }

        // Profile to subscribe to

        $other_id = $this->arg('subscribeto');

        $this->other = Profile::getKV('id', $other_id);

        if (empty($this->other)) {
            // TRANS: Client error displayed trying to subscribe to a non-existing profile.
            $this->clientError(_('No such profile.'));
            return false;
        }

        return true;
    }

    /**
     * Handle request
     *
     * Does the subscription and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        // Throws exception on error

        $sub = Subscription::start($this->user->getProfile(),
                                   $this->other);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title when subscription succeeded.
            $this->element('title', null, _('Subscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            if ($sub instanceof Subscription) {
                $form = new UnsubscribeForm($this, $this->other);
            } else {
                $form = new CancelSubscriptionForm($this, $this->other);
            }
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            $url = common_local_url('subscriptions',
                                    array('nickname' => $this->user->nickname));
            common_redirect($url, 303);
        }
    }
}

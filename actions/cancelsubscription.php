<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Leave a group
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Leave a group
 *
 * This is the action for leaving a group. It works more or less like the subscribe action
 * for users.
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class CancelsubscriptionAction extends Action
{

    function handle($args)
    {
        parent::handle($args);
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return;
        }

        $user = common_current_user();

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('subscriptions',
                                             array('nickname' => $user->nickname)));
            return;
        }

        /* Use a session token for CSRF protection. */

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token. ' .
                                 'Try again, please.'));
            return;
        }

        $other_id = $this->arg('unsubscribeto');

        if (!$other_id) {
            // TRANS: Client error displayed when trying to leave a group without specifying an ID.
            $this->clientError(_('No profile ID in request.'));
            return;
        }

        $other = Profile::getKV('id', $other_id);

        if (!$other) {
            // TRANS: Client error displayed when trying to leave a non-existing group.
            $this->clientError(_('No profile with that ID.'));
            return;
        }

        $this->request = Subscription_queue::pkeyGet(array('subscriber' => $user->id,
                                                           'subscribed' => $other->id));

        if (empty($this->request)) {
            // TRANS: Client error displayed when trying to approve a non-existing group join request.
            // TRANS: %s is a user nickname.
            $this->clientError(sprintf(_('%s is not in the moderation queue for this group.'), $this->profile->nickname), 403);
        }

        $this->request->abort();

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title after unsubscribing from a group.
            $this->element('title', null, _m('TITLE','Unsubscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $subscribe = new SubscribeForm($this, $other);
            $subscribe->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('subscriptions',
                                             array('nickname' => $user->nickname)),
                            303);
        }
    }
}

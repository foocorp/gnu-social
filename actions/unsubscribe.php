<?php
/**
 * Unsubscribe handler
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
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Unsubscribe handler
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class UnsubscribeAction extends Action
{
    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('subscriptions',
                                             array('nickname' => $this->scoped->nickname)));
        }

        /* Use a session token for CSRF protection. */

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token. ' .
                                 'Try again, please.'));
        }

        $other_id = $this->arg('unsubscribeto');

        if (!$other_id) {
            // TRANS: Client error displayed when trying to unsubscribe without providing a profile ID.
            $this->clientError(_('No profile ID in request.'));
        }

        $other = Profile::getKV('id', $other_id);

        if (!($other instanceof Profile)) {
            // TRANS: Client error displayed when trying to unsubscribe while providing a non-existing profile ID.
            $this->clientError(_('No profile with that ID.'));
        }

        try {
            Subscription::cancel($this->scoped, $other);
        } catch (Exception $e) {
            $this->clientError($e->getMessage());
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title for page to unsubscribe.
            $this->element('title', null, _('Unsubscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $subscribe = new SubscribeForm($this, $other);
            $subscribe->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect(common_local_url('subscriptions', array('nickname' => $this->scoped->nickname)), 303);
        }
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Single subscription
 *
 * PHP version 5
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
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Show a single subscription
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AtompubshowsubscriptionAction extends ApiAuthAction
{
    private $_subscriber   = null;
    private $_subscribed   = null;
    private $_subscription = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);
        $subscriberId = $this->trimmed('subscriber');

        $this->_subscriber = Profile::getKV('id', $subscriberId);

        if (empty($this->_subscriber)) {
            // TRANS: Client exception thrown when trying to display a subscription for a non-existing profile ID.
            // TRANS: %d is the non-existing profile ID number.
            throw new ClientException(sprintf(_('No such profile id: %d.'),
                                              $subscriberId), 404);
        }

        $subscribedId = $this->trimmed('subscribed');

        $this->_subscribed = Profile::getKV('id', $subscribedId);

        if (empty($this->_subscribed)) {
            // TRANS: Client exception thrown when trying to display a subscription for a non-existing profile ID.
            // TRANS: %d is the non-existing profile ID number.
            throw new ClientException(sprintf(_('No such profile id: %d.'),
                                              $subscribedId), 404);
        }

        $this->_subscription =
            Subscription::pkeyGet(array('subscriber' => $subscriberId,
                                        'subscribed' => $subscribedId));

        if (empty($this->_subscription)) {
            // TRANS: Client exception thrown when trying to display a subscription for a non-subscribed profile ID.
            // TRANS: %1$d is the non-existing subscriber ID number, $2$d is the ID of the profile that was not subscribed to.
            $msg = sprintf(_('Profile %1$d not subscribed to profile %2$d.'),
                           $subscriberId, $subscribedId);
            throw new ClientException($msg, 404);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'HEAD':
        case 'GET':
            $this->showSubscription();
            break;
        case 'DELETE':
            $this->deleteSubscription();
            break;
        default:
            // TRANS: Client error shown when using a non-supported HTTP method.
            $this->clientError(_('HTTP method not supported.'), 405);
            return;
        }

        return;
    }

    /**
     * Show the subscription in ActivityStreams Atom format.
     *
     * @return void
     */
    function showSubscription()
    {
        $activity = $this->_subscription->asActivity();

        header('Content-Type: application/atom+xml; charset=utf-8');

        $this->startXML();
        $this->raw($activity->asString(true, true, true));
        $this->endXML();

        return;
    }

    /**
     * Delete the subscription
     *
     * @return void
     */
    function deleteSubscription()
    {
        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_subscriber->id) {
            // TRANS: Client exception thrown when trying to delete a subscription of another user.
            throw new ClientException(_("Cannot delete someone else's ".
                                        "subscription."), 403);
        }

        Subscription::cancel($this->_subscriber,
                             $this->_subscribed);

        return;
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Return last modified, if applicable.
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        return max(strtotime($this->_subscriber->modified),
                   strtotime($this->_subscribed->modified),
                   strtotime($this->_subscription->modified));
    }

    /**
     * Etag for this object
     *
     * @return string etag http header
     */
    function etag()
    {
        $mtime = strtotime($this->_subscription->modified);

        return 'W/"' . implode(':', array('AtomPubShowSubscription',
                                          $this->_subscriber->id,
                                          $this->_subscribed->id,
                                          $mtime)) . '"';
    }

    /**
     * Does this require authentication?
     *
     * @return boolean true if delete, else false
     */
    function requiresAuth()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            return true;
        } else {
            return false;
        }
    }
}

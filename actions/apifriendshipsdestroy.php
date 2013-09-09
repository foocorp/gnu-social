<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Unsubscribe to a user via API
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
 * @category  API
 * @package   StatusNet
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Allows the authenticating users to unfollow (unsubscribe) the user specified in
 * the ID parameter.  Returns the unfollowed user in the requested format when
 * successful.  Returns a string describing the failure condition when unsuccessful.
 *
 * @category API
 * @package  StatusNet
 * @author   Dan Moore <dan@moore.cx>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiFriendshipsDestroyAction extends ApiAuthAction
{
    var $other  = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = $this->auth_user;
        $this->other  = $this->getTargetProfile($this->arg('id'));

        return true;
    }

    /**
     * Handle the request
     *
     * Check the format and show the user info
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                // TRANS: Client error. POST is a HTTP command. It should not be translated.
                _('This method requires a POST.'),
                400,
                $this->format
            );
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        if (empty($this->other)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to unfollow a user that cannot be found.
                _('Could not unfollow user: User not found.'),
                403,
                $this->format
            );
            return;
        }

        // Don't allow unsubscribing from yourself!

        if ($this->user->id == $this->other->id) {
            $this->clientError(
                // TRANS: Client error displayed when trying to unfollow self.
                _("You cannot unfollow yourself."),
                403,
                $this->format
            );
            return;
        }

        // throws an exception on error
        Subscription::cancel($this->user->getProfile(), $this->other);

        $this->initDocument($this->format);
        $this->showProfile($this->other, $this->format);
        $this->endDocument($this->format);
    }
}

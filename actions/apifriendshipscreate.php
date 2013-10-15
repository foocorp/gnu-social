<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Subscribe to a user via the API
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
 * Allows the authenticating users to follow (subscribe) the user specified in
 * the ID parameter.  Returns the befriended user in the requested format when
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
class ApiFriendshipsCreateAction extends ApiAuthAction
{
    protected $needPost = true;

    var $other  = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */
    protected function prepare($args)
    {
        parent::prepare($args);

        $this->other  = $this->getTargetProfile($this->arg('id'));

        return true;
    }

    /**
     * Handle the request
     *
     * Check the format and show the user info
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        if (!in_array($this->format, array('xml', 'json'))) {
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
        }

        if (empty($this->other)) {
            // TRANS: Client error displayed when trying follow who's profile could not be found.
            $this->clientError(_('Could not follow user: profile not found.'), 403);
        }

        if ($this->user->isSubscribed($this->other)) {
            $errmsg = sprintf(
                // TRANS: Client error displayed when trying to follow a user that's already being followed.
                // TRANS: %s is the nickname of the user that is already being followed.
                _('Could not follow user: %s is already on your list.'),
                $this->other->nickname
            );
            $this->clientError($errmsg, 403);
        }

        try {
            Subscription::start($this->user->getProfile(), $this->other);
        } catch (Exception $e) {
            $this->clientError($e->getMessage(), 403);
        }

        $this->initDocument($this->format);
        $this->showProfile($this->other, $this->format);
        $this->endDocument($this->format);
    }
}

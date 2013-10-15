<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Un-block a user via the API
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
 * Un-blocks the user specified in the ID parameter for the authenticating user.
 * Returns the un-blocked user in the requested format when successful.
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiBlockDestroyAction extends ApiAuthAction
{
    protected $needPost = true;

    var $other   = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
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
     * Save the new message
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        if (empty($this->user) || empty($this->other)) {
            // TRANS: Client error when user not found for an API action to remove a block for a user.
            $this->clientError(_('No such user.'), 404);
        }

        if ($this->user->hasBlocked($this->other)) {
            if (Event::handle('StartUnblockProfile', array($this->user, $this->other))) {
                $result = $this->user->unblock($this->other);
                if ($result) {
                    Event::handle('EndUnblockProfile', array($this->user, $this->other));
                }
            }
        }

        if (!$this->user->hasBlocked($this->other)) {
            $this->initDocument($this->format);
            $this->showProfile($this->other, $this->format);
            $this->endDocument($this->format);
        } else {
            // TRANS: Server error displayed when unblocking a user has failed.
            $this->serverError(_('Unblock user failed.'));
        }
    }
}

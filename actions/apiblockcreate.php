<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Block a user via the API
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
 * Blocks the user specified in the ID parameter as the authenticating user.
 * Destroys a friendship to the blocked user if it exists. Returns the
 * blocked user in the requested format when successful.
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiBlockCreateAction extends ApiAuthAction
{
    protected $needPost = true;

    var $other   = null;

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
     * Save the new message
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        if (empty($this->user) || empty($this->other)) {
            // TRANS: Client error displayed when trying to block a non-existing user or a user from another site.
            $this->clientError(_('No such user.'), 404);
        }

        // Don't allow blocking yourself!

        if ($this->user->id == $this->other->id) {
            // TRANS: Client error displayed when users try to block themselves.
            $this->clientError(_("You cannot block yourself!"), 403);
        }

        if (!$this->user->hasBlocked($this->other)) {
            if (Event::handle('StartBlockProfile', array($this->user, $this->other))) {
                $result = $this->user->block($this->other);
                if ($result) {
                    Event::handle('EndBlockProfile', array($this->user, $this->other));
                }
            }
        }

        if ($this->user->hasBlocked($this->other)) {
            $this->initDocument($this->format);
            $this->showProfile($this->other, $this->format);
            $this->endDocument($this->format);
        } else {
            // TRANS: Server error displayed when blocking a user has failed.
            $this->serverError(_('Block user failed.'), 500);
        }
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Return a user's avatar image
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiprivateauth.php';

/**
 * Ouputs avatar URL for a user, specified by screen name.
 * Unlike most API endpoints, this returns an HTTP redirect rather than direct data.
 *
 * @category API
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiUserProfileImageAction extends ApiPrivateAuthAction
{
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
        $this->user = User::getKV('nickname', $this->arg('screen_name'));
        $this->size = $this->arg('size');

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

        if (empty($this->user)) {
            // TRANS: Client error displayed when requesting user information for a non-existing user.
            $this->clientError(_('User not found.'), 404, $this->format);
            return;
        }

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->clientError(_('User has no profile.'));
            return;
        }

        $size = $this->avatarSize();
        $url  = $profile->avatarUrl($size);

        // We don't actually output JSON or XML data -- redirect!
        common_redirect($url, 302);
    }

    /**
     * Get the appropriate pixel size for an avatar based on the request...
     *
     * @return int
     */
    private function avatarSize()
    {
        switch ($this->size) {
            case 'mini':
                return AVATAR_MINI_SIZE; // 24x24
            case 'bigger':
                return AVATAR_PROFILE_SIZE; // Twitter does 73x73, but we do 96x96
            case 'normal': // fall through
            default:
                return AVATAR_STREAM_SIZE; // 48x48
        }
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}

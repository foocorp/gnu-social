<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update the authenticating user's profile
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * API analog to the profile settings page
 * Only the parameters specified will be updated.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAccountUpdateProfileAction extends ApiAuthAction
{
    protected $needPost = true;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->user = $this->auth_user;

        $this->name        = $this->trimmed('name');
        $this->url         = $this->trimmed('url');
        $this->location    = $this->trimmed('location');
        $this->description = $this->trimmed('description');

        return true;
    }

    /**
     * Handle the request
     *
     * See which request params have been set, and update the profile
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

        if (empty($this->user)) {
            // TRANS: Client error displayed if a user could not be found.
            $this->clientError(_('No such user.'), 404);
        }

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->clientError(_('User has no profile.'));
        }

        $original = clone($profile);

        if (!empty($this->name)) {
            $profile->fullname = $this->name;
        }

        if (!empty($this->url)) {
            $profile->homepage = $this->url;
        }

        if (!empty($this->description)) {
            $profile->bio = $this->description;
        }

        if (!empty($this->location)) {
            $profile->location = $this->location;

            $loc = Location::fromName($location);

            if (!empty($loc)) {
                $profile->lat         = $loc->lat;
                $profile->lon         = $loc->lon;
                $profile->location_id = $loc->location_id;
                $profile->location_ns = $loc->location_ns;
            }
        }

        $result = $profile->update($original);

        if (!$result) {
            common_log_db_error($profile, 'UPDATE', __FILE__);
            // TRANS: Server error displayed if a user profile could not be saved.
            $this->serverError(_('Could not save profile.'));
        }

        $twitter_user = $this->twitterUserArray($profile, true);

        if ($this->format == 'xml') {
            $this->initDocument('xml');
            $this->showTwitterXmlUser($twitter_user, 'user', true);
            $this->endDocument('xml');
        } elseif ($this->format == 'json') {
            $this->initDocument('json');
            $this->showJsonObjects($twitter_user);
            $this->endDocument('json');
        }
    }
}

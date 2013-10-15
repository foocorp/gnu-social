<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update the authenticating user notification channels
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
 * @author    Siebrand Mazeland <s.mazeland@xs4all.nl>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Sets which channel (device) StatusNet delivers updates to for
 * the authenticating user. Sending none as the device parameter
 * will disable IM and/or SMS updates.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAccountUpdateDeliveryDeviceAction extends ApiAuthAction
{
    protected $needPost = true;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = $this->auth_user;
        $this->device = $this->trimmed('device');

        return true;
    }

    /**
     * Handle the request
     *
     * See which request params have been set, and update the user settings
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        // Note: Twitter no longer supports IM

        if (!in_array(strtolower($this->device), array('sms', 'im', 'none'))) {
            // TRANS: Client error displayed when no valid device parameter is provided for a user's delivery device setting.
            $this->clientError(_( 'You must specify a parameter named ' .
                                  '\'device\' with a value of one of: sms, im, none.' ));
            return;
        }

        if (empty($this->user)) {
            // TRANS: Client error displayed when no existing user is provided for a user's delivery device setting.
            $this->clientError(_('No such user.'), 404);
        }

        $original = clone($this->user);

        if (strtolower($this->device) == 'sms') {
            $this->user->smsnotify = true;
        } elseif (strtolower($this->device) == 'im') {
            //TODO IM is pluginized now, so what should we do?
            //Enable notifications for all IM plugins?
            //For now, don't do anything
            //$this->user->jabbernotify = true;
        } elseif (strtolower($this->device == 'none')) {
            $this->user->smsnotify    = false;
            //TODO IM is pluginized now, so what should we do?
            //Disable notifications for all IM plugins?
            //For now, don't do anything
            //$this->user->jabbernotify = false;
        }

        $result = $this->user->update($original);

        if ($result === false) {
            common_log_db_error($this->user, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when a user's delivery device cannot be updated.
            $this->serverError(_('Could not update user.'));
            return;
        }

        $profile = $this->user->getProfile();

        $twitter_user = $this->twitterUserArray($profile, true);

        // Note: this Twitter API method is retarded because it doesn't give
        // any success/failure information. Twitter's docs claim that the
        // notification field will change to reflect notification choice,
        // but that's not true; notification> is used to indicate
        // whether the auth user is following the user in question.

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

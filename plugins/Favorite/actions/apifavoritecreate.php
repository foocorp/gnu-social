<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Add a notice to a user's list of favorite notices via the API
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Favorites the status specified in the ID parameter as the authenticating user.
 * Returns the favorite status when successful.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiFavoriteCreateAction extends ApiAuthAction
{
    var $notice = null;

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
        $this->notice = Notice::getKV($this->arg('id'));
        if ($this->notice->repeat_of != '' ) {
                common_log(LOG_DEBUG, 'Trying to Fave '.$this->notice->id.', repeat of '.$this->notice->repeat_of);
                common_log(LOG_DEBUG, 'Will Fave '.$this->notice->repeat_of.' instead');
                $real_notice_id = $this->notice->repeat_of;
                $this->notice = Notice::getKV($real_notice_id);
        }

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

        if (empty($this->notice)) {
            $this->clientError(
                // TRANS: Client error displayed when requesting a status with a non-existing ID.
                _('No status found with that ID.'),
                404,
                $this->format
            );
            return;
        }

        // Note: Twitter lets you fave things repeatedly via API.

        if (Fave::existsForProfile($this->notice, $this->scoped)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to mark a notice favourite that already is a favourite.
                _('This status is already a favorite.'),
                403,
                $this->format
            );
            return;
        }

        $fave = Fave::addNew($this->user->getProfile(), $this->notice);

        if (empty($fave)) {
            $this->clientError(
                // TRANS: Client error displayed when marking a notice as favourite fails.
                _('Could not create favorite.'),
                403,
                $this->format
            );
            return;
        }

        $this->notify($fave, $this->notice, $this->user);
        $this->user->blowFavesCache();

        if ($this->format == 'xml') {
            $this->showSingleXmlStatus($this->notice);
        } elseif ($this->format == 'json') {
            $this->show_single_json_status($this->notice);
        }
    }

    /**
     * Notify the author of the favorite that the user likes their notice
     *
     * @param Favorite $fave   the favorite in question
     * @param Notice   $notice the notice that's been faved
     * @param User     $user   the user doing the favoriting
     *
     * @return void
     */
    function notify($fave, $notice, $user)
    {
        $other = User::getKV('id', $notice->profile_id);
        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $user, $notice);
            }
            // XXX: notify by IM
            // XXX: notify by SMS
        }
    }
}

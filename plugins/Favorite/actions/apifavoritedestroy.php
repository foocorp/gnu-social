<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Remote a notice from a user's list of favorite notices via the API
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
 * Un-favorites the status specified in the ID parameter as the authenticating user.
 * Returns the un-favorited status in the requested format when successful.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiFavoriteDestroyAction extends ApiAuthAction
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
                common_log(LOG_DEBUG, 'Trying to unFave '.$this->notice->id);
                common_log(LOG_DEBUG, 'Will unFave '.$this->notice->repeat_of.' instead');
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
                // TRANS: Client error displayed when trying to remove a favourite with an invalid ID.
                _('No status found with that ID.'),
                404,
                $this->format
            );
            return;
        }

        $fave            = new Fave();
        $fave->user_id   = $this->user->id;
        $fave->notice_id = $this->notice->id;

        if (!$fave->find(true)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to remove a favourite that was not a favourite.
                _('That status is not a favorite.'),
                403,
                $this->favorite
            );
            return;
        }

        $result = $fave->delete();

        if (!$result) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            $this->clientError(
                // TRANS: Client error displayed when removing a favourite has failed.
                _('Could not delete favorite.'),
                404,
                $this->format
            );
            return;
        }

        $this->user->blowFavesCache();

        if ($this->format == 'xml') {
            $this->showSingleXmlStatus($this->notice);
        } elseif ($this->format == 'json') {
            $this->show_single_json_status($this->notice);
        }
    }
}

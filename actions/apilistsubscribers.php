<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show/add/remove list subscribers.
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
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apilistusers.php';

class ApiListSubscribersAction extends ApiListUsersAction
{
    /**
     * Subscribe to list
     *
     * @return boolean success
     */
    function handlePost()
    {
        $result = Profile_tag_subscription::add($this->list,
                            $this->auth_user);

        if(empty($result)) {
            $this->clientError(
                // TRANS: Client error displayed when an unknown error occurs in the list subscribers action.
                _('An error occured.'),
                500,
                $this->format
            );
            return false;
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($this->list);
            break;
        case 'json':
            $this->showSingleJsonList($this->list);
            break;
        default:
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return false;
            break;
        }
    }

    function handleDelete()
    {
        $args = array('profile_tag_id' => $this->list->id,
                      'profile_id' => $this->auth_user->id);
        $ptag = Profile_tag_subscription::pkeyGet($args);

        if(empty($ptag)) {
            $this->clientError(
                // TRANS: Client error displayed when trying to unsubscribe from a non-subscribed list.
                _('You are not subscribed to this list.'),
                400,
                $this->format
            );
            return false;
        }

        Profile_tag_subscription::remove($this->list, $this->auth_user);

        if(empty($result)) {
            $this->clientError(
                // TRANS: Client error displayed when an unknown error occurs unsubscribing from a list.
                _('An error occured.'),
                500,
                $this->format
            );
            return false;
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($this->list);
            break;
        case 'json':
            $this->showSingleJsonList($this->list);
            break;
        default:
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            return false;
            break;
        }
        return true;
    }

    function getUsers()
    {
        $fn = array($this->list, 'getSubscribers');
        list($this->users, $this->next_cursor, $this->prev_cursor) =
            Profile_list::getAtCursor($fn, array(), $this->cursor, 20);
    }
}

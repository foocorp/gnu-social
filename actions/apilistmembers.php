<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List/add/remove list members.
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
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apilistusers.php';

class ApiListMembersAction extends ApiListUsersAction
{
    /**
     * Add a user to a list (tag someone)
     *
     * @return boolean success
     */
    function handlePost()
    {
        if($this->auth_user->id != $this->list->tagger) {
            // TRANS: Client error displayed when trying to add members to a list without having the right to do so.
            $this->clientError(_('You are not allowed to add members to this list.'), 401);
        }

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed when trying to modify list members without specifying them.
            $this->clientError(_('You must specify a member.'));
        }

        $result = Profile_tag::setTag($this->auth_user->id,
                        $this->target->id, $this->list->tag);

        if(empty($result)) {
            // TRANS: Client error displayed when an unknown error occurs viewing list members.
            $this->clientError(_('An error occured.'), 500);
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($this->list);
            break;
        case 'json':
            $this->showSingleJsonList($this->list);
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
        }
    }

    /**
     * Remove a user from a list (untag someone)
     *
     * @return boolean success
     */
    function handleDelete()
    {
        if($this->auth_user->id != $this->list->tagger) {
            // TRANS: Client error displayed when trying to remove members from a list without having the right to do so.
            $this->clientError(_('You are not allowed to remove members from this list.'), 401);
        }

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed when trying to modify list members without specifying them.
            $this->clientError(_('You must specify a member.'));
        }

        $args = array('tagger' => $this->auth_user->id,
                      'tagged' => $this->target->id,
                      'tag' => $this->list->tag);
        $ptag = Profile_tag::pkeyGet($args);

        if (empty($ptag)) {
            // TRANS: Client error displayed when trying to remove a list member that is not part of a list.
            $this->clientError(_('The user you are trying to remove from the list is not a member.'));
        }

        if (!$ptag->delete()) {
            // TRANS: Client error displayed when an unknown error occurs viewing list members.
            $this->clientError(_('An error occured.'), 500);
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($this->list);
            break;
        case 'json':
            $this->showSingleJsonList($this->list);
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
        }

        return true;
    }

    /**
     * List the members of a list (people tagged)
     */
    function getUsers()
    {
        $fn = array($this->list, 'getTagged');
        list($this->users, $this->next_cursor, $this->prev_cursor) =
            Profile_list::getAtCursor($fn, array(), $this->cursor, 20);
    }
}

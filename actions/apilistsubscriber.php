<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Check if a user is subscribed to a list
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

class ApiListSubscriberAction extends ApiBareAuthAction
{
    var $list   = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->user = $this->getTargetUser($this->arg('id'));
        $this->list = $this->getTargetList($this->arg('user'), $this->arg('list_id'));

        if (empty($this->list)) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing list.
            $this->clientError(_('List not found.'), 404, $this->format);
            return false;
        }

        if (empty($this->user)) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing user.
            $this->clientError(_('No such user.'), 404, $this->format);
            return false;
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        $arr = array('profile_tag_id' => $this->list->id,
                      'profile_id' => $this->user->id);
        $sub = Profile_tag_subscription::pkeyGet($arr);

        if(empty($sub)) {
            $this->clientError(
                // TRANS: Client error displayed when a membership check for a user is nagative.
                _('The specified user is not a subscriber of this list.'),
                400,
                $this->format
            );
        }

        $user = $this->twitterUserArray($this->user->getProfile(), true);

        switch($this->format) {
        case 'xml':
            $this->showTwitterXmlUser($user, 'user', true);
            break;
        case 'json':
            $this->showSingleJsonUser($user);
            break;
        default:
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }
}

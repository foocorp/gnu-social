<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Lists a user is in
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
 * @category  Widget
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * List a user has is in
 *
 * @category Widget
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagsForUserSection extends PeopletagSection
{
    var $profile=null;

    function __construct($out, Profile $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;
    }

    function getPeopletags()
    {
        $limit = PEOPLETAGS_PER_SECTION+1;
        $offset = 0;

        $ptags = $this->profile->getOtherTags(Profile::current(), $offset, $limit);

        return $ptags;
    }

    function title()
    {
        $user = common_current_user();

        if (!empty($user) && $this->profile->id == $user->id) {
            // TRANS: Title for page that displays which lists current user is part of.
            return sprintf(_('Lists with you'));
        }
        // TRANS: Title for page that displays which lists a user is part of.
        // TRANS: %s is a profile name.
        return sprintf(_('Lists with %s'), $this->profile->getBestName());
    }

    function link()
    {
        return common_local_url('peopletagsforuser',
                array('nickname' => $this->profile->nickname));
    }

    function moreUrl()
    {
        return $this->link();
    }

    function divId()
    {
        return 'peopletag_subscriptions';
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Peopletags a user has subscribed to
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
 * Peopletags a user has subscribed to
 *
 * @category Widget
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagSubscriptionsSection extends PeopletagSection
{
    var $profile=null;
    var $ptags=null;

    function __construct($out, Profile $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;

        $limit = PEOPLETAGS_PER_SECTION+1;
        $offset = 0;

        $this->ptags = $this->profile->getTagSubscriptions($offset, $limit);
    }

    function getPeopletags()
    {
        return $this->ptags;
    }

    function title()
    {
        // TRANS: Title for page that displays lists a user has subscribed to.
        return _('List subscriptions');
    }

    function link()
    {
        return common_local_url('peopletagsubscriptions',
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

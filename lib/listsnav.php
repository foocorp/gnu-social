<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Lists a user has created
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
class ListsNav extends MoreMenu
{
    var $profile=null;
    var $lists=null;

    function __construct($out, Profile $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;

        $this->lists = $profile->getLists(Profile::current());
    }

    function tag()
    {
        return 'lists';
    }

    function getItems()
    {
        $items = array();

        while ($this->lists->fetch()) {
                $mode = $this->lists->private ? 'private' : 'public';
                $items[] = array('showprofiletag',
                                 array('nickname' => $this->profile->getNickname(),
                                       'tag'    => $this->lists->tag),
                                 $this->lists->tag,
                                 '');
         }

         return $items;
    }

    function hasLists()
    {
        return (!empty($this->lists) && $this->lists->N > 0);
    }

    function seeAllItem()
    {
        return array('peopletagsbyuser',
                     array('nickname' => $this->profile->nickname),
                     // TRANS: Link description for seeing all lists.
                     _('See all'),
                     // TRANS: Link title for seeing all lists.
                     _('See all lists you have created.'));
    }
}

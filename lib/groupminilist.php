<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a mini-list of groups
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
 * @category  Public
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/grouplist.php';

define('GROUPS_PER_MINILIST', 8);

/**
 * Widget to show a list of groups, good for sidebar
 *
 * @category Public
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupMiniList extends GroupList
{
    function show()
    {
        $this->out->elementStart('ul', 'entities groups xoxo');

        $cnt = 0;

        while ($this->group->fetch()) {
            $cnt++;
            if($cnt > GROUPS_PER_MINILIST) {
                break;
            }
            $this->showGroup();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showGroup()
    {
        $this->out->elementStart('li', 'vcard');
        $this->out->elementStart('a', array('title' => ($this->group->fullname) ?
                                       $this->group->fullname :
                                       $this->group->nickname,
                                       'href' => $this->group->homeUrl(),
                                       'rel' => 'contact group',
                                       'class' => 'url'));

        $logo = ($this->group->mini_logo) ?
          $this->group->mini_logo : User_group::defaultLogo(AVATAR_MINI_SIZE);

        $this->out->element('img', array('src' => $logo,
                                    'width' => AVATAR_MINI_SIZE,
                                    'height' => AVATAR_MINI_SIZE,
                                    'class' => 'avatar photo',
                                    'alt' =>  ($this->group->fullname) ?
                                    $this->group->fullname :
                                    $this->group->nickname));
        $this->out->element('span', 'fn org nickname', $this->group->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('li');
    }
}

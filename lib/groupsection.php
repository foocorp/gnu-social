<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of groups
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('GROUPS_PER_SECTION', 6);

/**
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupSection extends Section
{
    function showContent()
    {
        $profiles = $this->getGroups();

        if (!$profiles) {
            return false;
        }

        $cnt = 0;

        $this->out->elementStart('table');

        while ($profiles->fetch() && ++$cnt <= GROUPS_PER_SECTION) {
            $this->showGroup($profiles);
        }

        $this->out->elementEnd('table');

        return ($cnt > GROUPS_PER_SECTION);
    }

    function getGroups()
    {
        return null;
    }

    function showGroup($group)
    {
        $this->out->elementStart('tr');
        $this->out->elementStart('td');
        $this->out->elementStart('a', array('title' => $group->getBestName(),
                                            'href' => $group->homeUrl(),
                                            'rel' => 'contact group',
                                            'class' => 'h-card org nickname'));
        $logo = $group->stream_logo ?: User_group::defaultLogo(AVATAR_STREAM_SIZE);
        $this->out->element('img', array('src' => $logo,
                                         'width' => AVATAR_MINI_SIZE,
                                         'height' => AVATAR_MINI_SIZE,
                                         'class' => 'avatar u-photo',
                                         'alt' =>  $group->getBestName()));
        $this->out->text($group->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('td');
        if ($group->value) {
            $this->out->element('td', 'value', $group->value);
        }
        $this->out->elementEnd('tr');
    }
}

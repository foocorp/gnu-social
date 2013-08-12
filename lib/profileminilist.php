<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of profiles
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

require_once INSTALLDIR.'/lib/profilelist.php';

define('PROFILES_PER_MINILIST', 8);

/**
 * Widget to show a list of profiles, good for sidebar
 *
 * @category Public
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ProfileMiniList extends ProfileList
{
    const MAX_PROFILES = PROFILES_PER_MINILIST; // put it in the class

    function startList()
    {
        $this->out->elementStart('ul', 'entities users xoxo');
    }

    function newListItem($profile)
    {
        return new ProfileMiniListItem($profile, $this->action);
    }

    function maxProfiles()
    {
        return PROFILES_PER_MINILIST;
    }

    function avatarSize()
    {
        return AVATAR_MINI_SIZE;
    }
}

class ProfileMiniListItem extends ProfileListItem
{
    function show()
    {
        $this->out->elementStart('li', 'vcard');
        if (Event::handle('StartProfileListItemProfileElements', array($this))) {
            if (Event::handle('StartProfileListItemAvatar', array($this))) {
                $aAttrs = $this->linkAttributes();
                $this->out->elementStart('a', $aAttrs);
                $avatar = $this->profile->getAvatar(AVATAR_MINI_SIZE);
                $this->out->element('img', array('src' => (($avatar) ? $avatar->displayUrl() :  Avatar::defaultImage(AVATAR_MINI_SIZE)),
                                                 'width' => AVATAR_MINI_SIZE,
                                                 'height' => AVATAR_MINI_SIZE,
                                                 'class' => 'avatar photo',
                                                 'alt' =>  ($this->profile->fullname) ?
                                                 $this->profile->fullname :
                                                 $this->profile->nickname));
                $this->out->element('span', 'fn nickname', $this->profile->nickname);
                $this->out->elementEnd('a');
                Event::handle('EndProfileListItemAvatar', array($this));
            }
            $this->out->elementEnd('li');
        }
    }

    // default; overridden for nofollow lists

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        $aAttrs['title'] = $this->profile->getBestName();
        $aAttrs['rel']   = 'contact member'; // @todo: member? always?
        $aAttrs['class'] = 'url';

        return $aAttrs;
    }
}

<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Superclass for profile blocks
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Class comment
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

abstract class ProfileBlock extends Widget
{
    abstract function avatar();
    abstract function name();
    abstract function url();
    abstract function location();
    abstract function homepage();
    abstract function description();

    function show()
    {
        $this->showActions();
        $this->showAvatar();
        $this->showName();
        $this->showLocation();
        $this->showHomepage();
        $this->showOtherProfiles();
        $this->showDescription();
        $this->showTags();
    }

    function showAvatar()
    {
        $size = $this->avatarSize();

        $this->out->element(
            'img',
            array(
                'src'  => $this->avatar(),
                'class'  => 'ur_face',
                'alt'    => $this->name(),
                'width'  => $size,
                'height' => $size
            )
        );
    }

    function showName()
    {
        $name = $this->name();

        if (!empty($name)) {
            $this->out->elementStart('p', 'profile_block_name');
            $url = $this->url();
            if (!empty($url)) {
                $this->out->element('a', array('href' => $url),
                                    $name);
            } else {
                $this->out->text($name);
            }
            $this->out->elementEnd('p');
        }
    }

    function showDescription()
    {
        $description = $this->description();

        if (!empty($description)) {
            $this->out->element(
                'p',
                'profile_block_description',
                $description
            );
        }
    }

    function showLocation()
    {
        $location = $this->location();

        if (!empty($location)) {
            $this->out->element('p', 'profile_block_location', $location);
        }
    }

    function showHomepage()
    {
        $homepage = $this->homepage();

        if (!empty($homepage)) {
            $this->out->element('a',
                                array('href' => $homepage,
                                      'rel' => 'me',
                                      'class' => 'profile_block_homepage'),
                                $homepage);
        }
    }

    function showOtherProfiles()
    {
        $otherProfiles = $this->otherProfiles();

        if (!empty($otherProfiles)) {

            $this->out->elementStart('ul',
                                     array('class' => 'profile_block_otherprofile_list'));

            foreach ($otherProfiles as $otherProfile) {
                $this->out->elementStart('li');
                $this->out->elementStart('a',
                                         array('href' => $otherProfile['href'],
                                               'rel' => 'me',
                                               'class' => 'profile_block_otherprofile',
                                               'title' => $otherProfile['text']));
                $this->out->element('img',
                                    array('src' => $otherProfile['image'],
                                          'class' => 'profile_block_otherprofile_icon'));
                $this->out->elementEnd('a');
                $this->out->elementEnd('li');
            }

            $this->out->elementEnd('ul');
        }
    }

    function avatarSize()
    {
        return AVATAR_PROFILE_SIZE;
    }

    function showTags()
    {
    }

    function showActions()
    {
    }
}

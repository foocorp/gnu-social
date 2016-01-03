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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget to show a list of profiles
 *
 * @category Public
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ProfileList extends Widget
{
    /** Current profile, profile query. */
    var $profile = null;
    /** Action object using us. */
    var $action = null;

    function __construct($profile, HTMLOutputter $action=null)
    {
        parent::__construct($action);

        $this->profile = $profile;
        $this->action = $action;
    }

    function show()
    {
        $cnt = 0;

        if (Event::handle('StartProfileList', array($this))) {
            $this->startList();
            $cnt = $this->showProfiles();
            $this->endList();
            Event::handle('EndProfileList', array($this));
        }

        return $cnt;
    }

    function startList()
    {
        $this->out->elementStart('ul', 'profile_list xoxo');
    }

    function endList()
    {
        $this->out->elementEnd('ul');
    }

    function showProfiles()
    {
        $cnt = $this->profile->N;
        $profiles = $this->profile->fetchAll();

        $max = min($cnt, $this->maxProfiles());

        for ($i = 0; $i < $max; $i++) {
            $pli = $this->newListItem($profiles[$i]);
            $pli->show();
        }

        return $cnt;
    }

    function newListItem(Profile $target)
    {
        return new ProfileListItem($target, $this->action);
    }

    function maxProfiles()
    {
        return PROFILES_PER_PAGE;
    }
}

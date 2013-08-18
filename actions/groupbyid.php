<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Permalink for group
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

/**
 * Permalink for a group
 *
 * The group nickname can change, but not the group ID. So we use
 * an URL with the ID in it as the permanent identifier.
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupbyidAction extends Action
{
    /** group we're viewing. */
    var $group = null;

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->arg('id');

        if (!$id) {
            // TRANS: Client error displayed referring to a group's permalink without providing a group ID.
            $this->clientError(_('No ID.'));
            return false;
        }

        common_debug("Got ID $id");

        $this->group = User_group::getKV('id', $id);

        if (!$this->group) {
            // TRANS: Client error displayed referring to a group's permalink for a non-existing group ID.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Shows a profile for the group, some controls, and a list of
     * group notices.
     *
     * @return void
     */
    function handle($args)
    {
        common_redirect($this->group->homeUrl(), 303);
    }
}

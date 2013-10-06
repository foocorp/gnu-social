<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List a group's members
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * List 20 newest members of the group specified by name or ID.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiGroupMembershipAction extends ApiPrivateAuthAction
{
    var $group    = null;
    var $profiles = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->group    = $this->getTargetGroup($this->arg('id'));
        if (empty($this->group)) {
            // TRANS: Client error displayed trying to show group membership on a non-existing group.
            $this->clientError(_('Group not found.'), 404, $this->format);
            return false;
        }

        $this->profiles = $this->getProfiles();

        return true;
    }

    /**
     * Handle the request
     *
     * Show the members of the group
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        // XXX: RSS and Atom

        switch($this->format) {
        case 'xml':
            $this->showTwitterXmlUsers($this->profiles);
            break;
        case 'json':
            $this->showJsonUsers($this->profiles);
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

    /**
     * Fetch the members of a group
     *
     * @return array $profiles list of profiles
     */
    function getProfiles()
    {
        $profiles = array();

        $profile = $this->group->getMembers(
            ($this->page - 1) * $this->count,
            $this->count,
            $this->since_id,
            $this->max_id
        );

        while ($profile->fetch()) {
            $profiles[] = clone($profile);
        }

        return $profiles;
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this list of profiles last modified?
     *
     * @return string datestamp of the lastest profile in the group
     */
    function lastModified()
    {
        if (!empty($this->profiles) && (count($this->profiles) > 0)) {
            return strtotime($this->profiles[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this list of groups
     *
     * Returns an Etag based on the action name, language
     * the group id, and timestamps of the first and last
     * user who has joined the group
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->profiles) && (count($this->profiles) > 0)) {

            $last = count($this->profiles) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_user_cache_hash($this->auth_user),
                      common_language(),
                      $this->group->id,
                      strtotime($this->profiles[0]->created),
                      strtotime($this->profiles[$last]->created))
            )
            . '"';
        }

        return null;
    }
}

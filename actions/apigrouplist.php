<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Check to see whether a user a member of a group
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
 * Returns whether a user is a member of a specified group.
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
class ApiGroupListAction extends ApiBareAuthAction
{
    var $groups   = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare($args)
    {
        parent::prepare($args);

        //TODO: Make sure this doesn't leak unwantedly for federated users
        $this->target = $this->getTargetProfile(null);

        if (!($this->target instanceof Profile)) {
            // TRANS: Client error displayed when user not found for an action.
            $this->clientError(_('No such user.'), 404);
        }

        $this->groups = $this->getGroups();

        return true;
    }

    /**
     * Handle the request
     *
     * Show the user's groups
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        $sitename   = common_config('site', 'name');
        // TRANS: Used as title in check for group membership. %s is a user name.
        $title      = sprintf(_("%s's groups"), $this->target->nickname);
        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:Groups";
        $link       = common_local_url(
            'usergroups',
            array('nickname' => $this->target->nickname)
        );

        $subtitle   = sprintf(
            // TRANS: Used as subtitle in check for group membership. %1$s is the site name, %2$s is a user name.
            _('%1$s groups %2$s is a member of.'),
            $sitename,
            $this->target->nickname
        );

        switch($this->format) {
        case 'xml':
            $this->showXmlGroups($this->groups);
            break;
        case 'rss':
            $this->showRssGroups($this->groups, $title, $link, $subtitle);
            break;
        case 'atom':
            $selfuri = common_local_url('ApiGroupList', array('id'=>$this->target->id, 'format'=>'atom'));
            $this->showAtomGroups(
                $this->groups,
                $title,
                $id,
                $link,
                $subtitle,
                $selfuri
            );
            break;
        case 'json':
            $this->showJsonGroups($this->groups);
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), 404);
        }
    }

    /**
     * Get groups
     *
     * @return array groups
     */
    function getGroups()
    {
        $groups = array();

        $group = $this->target->getGroups(
            ($this->page - 1) * $this->count,
            $this->count,
            $this->since_id,
            $this->max_id
        );

        while ($group->fetch()) {
            $groups[] = clone($group);
        }

        return $groups;
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
     * When was this feed last modified?
     *
     * @return string datestamp of the latest group the user has joined
     */

    function lastModified()
    {
        if (!empty($this->groups) && (count($this->groups) > 0)) {
            return strtotime($this->groups[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this list of groups
     *
     * Returns an Etag based on the action name, language, user ID and
     * timestamps of the first and last group the user has joined
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->groups) && (count($this->groups) > 0)) {

            $last = count($this->groups) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_user_cache_hash($this->auth_user),
                      common_language(),
                      $this->target->id,
                      strtotime($this->groups[0]->created),
                      strtotime($this->groups[$last]->created))
            )
            . '"';
        }

        return null;
    }
}

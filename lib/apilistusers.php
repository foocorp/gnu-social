<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for list members and list subscribers api.
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
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ApiListUsersAction extends ApiBareAuthAction
{
    var $list   = null;
    var $user   = false;
    var $create = false;
    var $delete = false;
    var $cursor = -1;
    var $next_cursor = 0;
    var $prev_cursor = 0;
    var $users = null;

    protected function prepare(array $args=array())
    {
        // delete list member if method is DELETE or if method is POST and an argument
        // _method is set to DELETE
        $this->delete = ($_SERVER['REQUEST_METHOD'] == 'DELETE' ||
                            ($this->trimmed('_method') == 'DELETE' &&
                             $_SERVER['REQUEST_METHOD'] == 'POST'));

        // add member if method is POST
        $this->create = (!$this->delete &&
                         $_SERVER['REQUEST_METHOD'] == 'POST');

        if ($this->arg('id')) {
            $this->target = $this->getTargetProfile($this->arg('id'));
        }

        parent::prepare($args);

        $this->list = $this->getTargetList($this->arg('user'), $this->arg('list_id'));

        if (empty($this->list)) {
            // TRANS: Client error displayed when referring to a non-existing list.
            $this->clientError(_('List not found.'), 404, $this->format);
        }

        if(!$this->create && !$this->delete) {
            $this->getUsers();
        }
        return true;
    }

    function requiresAuth()
    {
        return parent::requiresAuth() ||
            $this->create || $this->delete;
    }

    protected function handle()
    {
        parent::handle();

        if($this->delete) {
            return $this->handleDelete();
        }

        if($this->create) {
            return $this->handlePost();
        }

        switch($this->format) {
        case 'xml':
            $this->initDocument('xml');
            $this->elementStart('users_list', array('xmlns:statusnet' =>
                                         'http://status.net/schema/api/1/'));
            $this->elementStart('users', array('type' => 'array'));

            if (is_array($this->users)) {
                foreach ($this->users as $u) {
                    $twitter_user = $this->twitterUserArray($u, true);
                    $this->showTwitterXmlUser($twitter_user);
                }
            } else {
                while ($this->users->fetch()) {
                    $twitter_user = $this->twitterUserArray($this->users, true);
                    $this->showTwitterXmlUser($twitter_user);
                }
            }

            $this->elementEnd('users');
            $this->element('next_cursor', null, $this->next_cursor);
            $this->element('previous_cursor', null, $this->prev_cursor);
            $this->elementEnd('users_list');
            break;
        case 'json':
            $this->initDocument('json');

            $users = array();

            if (is_array($this->users)) {
                foreach ($this->users as $u) {
                    $twitter_user = $this->twitterUserArray($u, true);
                    array_push($users, $twitter_user);
                }
            } else {
                while ($this->users->fetch()) {
                    $twitter_user = $this->twitterUserArray($this->users, true);
                    array_push($users, $twitter_user);
                }
            }

            $users_list = array('users' => $users,
                                'next_cursor' => $this->next_cursor,
                                'next_cursor_str' => strval($this->next_cursor),
                                'previous_cursor' => $this->prev_cursor,
                                'previous_cursor_str' => strval($this->prev_cursor));

            $this->showJsonObjects($users_list);

            $this->endDocument('json');
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

    function handlePost()
    {
    }

    function handleDelete()
    {
    }

    function getUsers()
    {
    }

    function isReadOnly($args)
    {
        return false;
    }

    function lastModified()
    {
        if(!empty($this->list)) {
            return strtotime($this->list->modified);
        }
        return null;
    }

    /**
     * An entity tag for this list
     *
     * Returns an Etag based on the action name, language, user ID and
     * timestamps of the first and last list the user has joined
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->list)) {

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->list->id,
                      strtotime($this->list->created),
                      strtotime($this->list->modified))
            )
            . '"';
        }

        return null;
    }
}

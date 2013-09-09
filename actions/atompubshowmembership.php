<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Show a single membership as an Activity Streams entry
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
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Show (or delete) a single membership event as an ActivityStreams entry
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AtompubshowmembershipAction extends ApiAuthAction
{
    private $_profile    = null;
    private $_group      = null;
    private $_membership = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $profileId = $this->trimmed('profile');

        $this->_profile = Profile::getKV('id', $profileId);

        if (empty($this->_profile)) {
            // TRANS: Client exception.
            throw new ClientException(_('No such profile.'), 404);
        }

        $groupId = $this->trimmed('group');

        $this->_group = User_group::getKV('id', $groupId);

        if (empty($this->_group)) {
            // TRANS: Client exception thrown when referencing a non-existing group.
            throw new ClientException(_('No such group.'), 404);
        }

        $kv = array('group_id' => $groupId,
                    'profile_id' => $profileId);

        $this->_membership = Group_member::pkeyGet($kv);

        if (empty($this->_membership)) {
            // TRANS: Client exception thrown when trying to show membership of a non-subscribed group
            throw new ClientException(_('Not a member.'), 404);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
        case 'HEAD':
            $this->showMembership();
            break;
        case 'DELETE':
            $this->deleteMembership();
            break;
        default:
            // TRANS: Client exception thrown when using an unsupported HTTP method.
            throw new ClientException(_('HTTP method not supported.'), 405);
            break;
        }
        return;
    }

    /**
     * show a single membership
     *
     * @return void
     */
    function showMembership()
    {
        $activity = $this->_membership->asActivity();

        header('Content-Type: application/atom+xml; charset=utf-8');

        $this->startXML();
        $this->raw($activity->asString(true, true, true));
        $this->endXML();

        return;
    }

    /**
     * Delete the membership (leave the group)
     *
     * @return void
     */
    function deleteMembership()
    {
        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_profile->id) {
            // TRANS: Client exception thrown when deleting someone else's membership.
            throw new ClientException(_("Cannot delete someone else's".
                                        " membership."), 403);
        }

        $this->auth_user->leaveGroup($this->_group);

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return last modified, if applicable.
     *
     * Because the representation depends on the profile and group,
     * our last modified value is the maximum of their mod time
     * with the actual membership's mod time.
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        return max(strtotime($this->_profile->modified),
                   strtotime($this->_group->modified),
                   strtotime($this->_membership->modified));
    }

    /**
     * Return etag, if applicable.
     *
     * A "weak" Etag including the profile and group id as well as
     * the admin flag and ctime of the membership.
     *
     * @return string etag http header
     */
    function etag()
    {
        $ctime = strtotime($this->_membership->created);

        $adminflag = ($this->_membership->is_admin) ? 't' : 'f';

        return 'W/"' . implode(':', array('AtomPubShowMembership',
                                          $this->_profile->id,
                                          $this->_group->id,
                                          $adminflag,
                                          $ctime)) . '"';
    }

    /**
     * Does this require authentication?
     *
     * @return boolean true if delete, else false
     */
    function requiresAuth()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return false;
        } else {
            return true;
        }
    }
}

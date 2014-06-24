<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Show a single favorite in Atom Activity Streams format
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
 * Show a single favorite in Atom Activity Streams format.
 *
 * Can also be used to delete a favorite.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AtompubshowfavoriteAction extends ApiAuthAction
{
    private $_profile = null;
    private $_notice  = null;
    private $_fave    = null;

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
        $noticeId  = $this->trimmed('notice');

        $this->_profile = Profile::getKV('id', $profileId);

        if (empty($this->_profile)) {
            // TRANS: Client exception.
            throw new ClientException(_('No such profile.'), 404);
        }

        $this->_notice = Notice::getKV('id', $noticeId);

        if (empty($this->_notice)) {
            // TRANS: Client exception thrown when referencing a non-existing notice.
            throw new ClientException(_('No such notice.'), 404);
        }

        $this->_fave = Fave::pkeyGet(array('user_id' => $profileId,
                                           'notice_id' => $noticeId));

        if (empty($this->_fave)) {
            // TRANS: Client exception thrown when referencing a non-existing favorite.
            throw new ClientException(_('No such favorite.'), 404);
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
        parent::handle($argarray);

        switch ($_SERVER['REQUEST_METHOD']) {
        case GET:
        case HEAD:
            $this->showFave();
            break;
        case DELETE:
            $this->deleteFave();
            break;
        default:
            // TRANS: Client exception thrown using an unsupported HTTP method.
            throw new ClientException(_('HTTP method not supported.'),
                                      405);
        }
        return true;
    }

    /**
     * Show a single favorite, in ActivityStreams format
     *
     * @return void
     */
    function showFave()
    {
        $activity = $this->_fave->asActivity();

        header('Content-Type: application/atom+xml; charset=utf-8');

        $this->startXML();
        $this->raw($activity->asString(true, true, true));
        $this->endXML();

        return;
    }

    /**
     * Delete the favorite
     *
     * @return void
     */
    function deleteFave()
    {
        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_profile->id) {
            // TRANS: Client exception thrown when trying to remove a favorite notice of another user.
            throw new ClientException(_("Cannot delete someone else's".
                                        " favorite."), 403);
        }

        $this->_fave->delete();

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
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        return max(strtotime($this->_profile->modified),
                   strtotime($this->_notice->modified),
                   strtotime($this->_fave->modified));
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        $mtime = strtotime($this->_fave->modified);

        return 'W/"' . implode(':', array('AtomPubShowFavorite',
                                          $this->_profile->id,
                                          $this->_notice->id,
                                          $mtime)) . '"';
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

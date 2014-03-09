<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * Peopletag autocomple action.
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
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PeopletagautocompleteAction extends Action
{
    var $user;
    var $tags;
    var $last_mod;

    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
        }

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
        }

        $profile = $this->user->getProfile();
        $tags = $profile->getLists(common_current_user());

        $this->tags = array();
        while ($tags->fetch()) {

            if (empty($this->last_mod)) {
                $this->last_mod = $tags->modified;
            }

            $arr = array();
            $arr['tag'] = $tags->tag;
            $arr['mode'] = $tags->private ? 'private' : 'public';
            // $arr['url'] = $tags->homeUrl();
            $arr['freq'] = $tags->taggedCount();

            $this->tags[] = $arr;
        }

        $tags = NULL;

        return true;
    }

    /**
     * Last modified time
     *
     * Helps in browser-caching
     *
     * @return String time
     */
    function lastModified()
    {
        return strtotime($this->last_mod);
    }

    /**
     * Handle request
     *
     * Print the JSON autocomplete data
     *
     * @param Array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        //common_log(LOG_DEBUG, 'Autocomplete data: ' . json_encode($this->tags));
        if ($this->tags) {
            print(json_encode($this->tags));
            exit(0);
        }
        return false;
    }
}

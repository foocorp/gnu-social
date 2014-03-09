<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * Action to add a people tag to a user.
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

require_once INSTALLDIR . '/lib/togglepeopletag.php';

/**
 *
 * Action to tag a profile with a single tag.
 *
 * Takes parameters:
 *
 *    - tagged: the ID of the profile being tagged
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *    - peopletag_id: the ID of the tag being used
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class AddpeopletagAction extends Action
{
    var $user;
    var $tagged;
    var $peopletag;

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

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
        }

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
        }

        // Profile to subscribe to

        $tagged_id = $this->arg('tagged');

        $this->tagged = Profile::getKV('id', $tagged_id);

        if (empty($this->tagged)) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing profile.
            $this->clientError(_('No such profile.'));
        }

        $id = $this->arg('peopletag_id');
        $this->peopletag = Profile_list::getKV('id', $id);

        if (empty($this->peopletag)) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            $this->clientError(_('No such list.'));
        }

        return true;
    }

    /**
     * Handle request
     *
     * Does the tagging and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        // Throws exception on error
        $ptag = Profile_tag::setTag($this->user->id, $this->tagged->id,
                                $this->peopletag->tag);

        if (!$ptag) {
            $user = User::getKV('id', $id);
            if ($user) {
                $this->clientError(
                        // TRANS: Client error displayed when an unknown error occurs when adding a user to a list.
                        // TRANS: %s is a username.
                        sprintf(_('There was an unexpected error while listing %s.'),
                        $user->nickname));
            } else {
                // TRANS: Client error displayed when an unknown error occurs when adding a user to a list.
                // TRANS: %s is a profile URL.
                $this->clientError(sprintf(_('There was a problem listing %s. ' .
                                      'The remote server is probably not responding correctly. ' .
                                      'Please try retrying later.'), $this->profile->profileurl));
            }
        }
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title after adding a user to a list.
            $this->element('title', null, _m('TITLE','Listed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $unsubscribe = new UntagButton($this, $this->tagged, $this->peopletag);
            $unsubscribe->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            $url = common_local_url('subscriptions',
                                    array('nickname' => $this->user->nickname));
            common_redirect($url, 303);
        }
    }
}

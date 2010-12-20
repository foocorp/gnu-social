<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Takes parameters:
 *
 *    - feed: a profile ID
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
abstract class BaseMirrorAction extends Action
{
    var $user;
    var $profile;

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
        return $this->sharedBoilerplate();
    }

    protected function validateFeedUrl($url)
    {
        if (common_valid_http_url($url)) {
            return $url;
        } else {
            $this->clientError(_m("Invalid feed URL."));
        }
    }

    protected function validateProfile($id)
    {
        $id = intval($id);
        $profile = Profile::staticGet('id', $id);
        if ($profile && $profile->id != $this->user->id) {
            return $profile;
        }
        // TRANS: Error message returned to user when setting up feed mirroring, but we were unable to resolve the given URL to a working feed.
        $this->clientError(_m("Invalid profile for mirroring."));
    }

    /**
     *
     * @param string $url
     * @return Profile
     */
    protected function profileForFeed($url)
    {
        try {
            // Maybe we got a web page?
            $oprofile = Ostatus_profile::ensureProfileURL($url);
        } catch (Exception $e) {
            // Direct feed URL?
            $oprofile = Ostatus_profile::ensureFeedURL($url);
        }
        if ($oprofile->isGroup()) {
            $this->clientError(_m("Can't mirror a StatusNet group at this time."));
        }
        $this->oprofile = $oprofile; // @fixme ugly side effect :D
        return $oprofile->localProfile();
    }

    /**
     * @todo FIXME: none of this belongs in end classes
     * this stuff belongs in shared code!
     */
    function sharedBoilerplate()
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_m('This action only accepts POST requests.'));
            return false;
        }

        // CSRF protection
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_m('There was a problem with your session token.'.
                                 ' Try again, please.'));
            return false;
        }

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            $this->clientError(_m('Not logged in.'));
            return false;
        }
        return true;
    }

    /**
     * Handle request
     *
     * Does the subscription and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        // Throws exception on error
        $this->saveMirror();

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _m('Subscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $unsubscribe = new EditMirrorForm($this, $this->profile);
            $unsubscribe->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $url = common_local_url('mirrorsettings');
            common_redirect($url, 303);
        }
    }

    abstract function saveMirror();
}

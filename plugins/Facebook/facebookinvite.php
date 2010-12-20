<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/Facebook/facebookaction.php';

class FacebookinviteAction extends FacebookAction
{
    function handle($args)
    {
        parent::handle($args);
        $this->showForm();
    }

    /**
     * Wrapper for showing a page
     *
     * Stores an error and shows the page
     *
     * @param string $error Error, if any
     *
     * @return void
     */
    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    /**
     * Show the page content
     *
     * Either shows the registration form or, if registration was successful,
     * instructions for using the site.
     *
     * @return void
     */
    function showContent()
    {
        if ($this->arg('ids')) {
            $this->showSuccessContent();
        } else {
            $this->showFormContent();
        }
    }

    function showSuccessContent()
    {
        // TRANS: %s is the name of the site.
        $this->element('h2', null, sprintf(_m('Thanks for inviting your friends to use %s.'),
            common_config('site', 'name')));
        // TRANS: Followed by an unordered list with invited friends.
        $this->element('p', null, _m('Invitations have been sent to the following users:'));

        $friend_ids = $_POST['ids']; // XXX: Hmm... is this the best way to access the list?

        $this->elementStart('ul', array('id' => 'facebook-friends'));

        foreach ($friend_ids as $friend) {
            $this->elementStart('li');
            $this->element('fb:profile-pic', array('uid' => $friend, 'size' => 'square'));
            $this->element('fb:name', array('uid' => $friend,
                                            'capitalize' => 'true'));
            $this->elementEnd('li');
        }

        $this->elementEnd('ul');
    }

    function showFormContent()
    {
        $content = sprintf(_m('You have been invited to %s'), common_config('site', 'name')) .
            htmlentities('<fb:req-choice url="' . $this->app_uri . '" label="Add"/>');

        $this->elementStart('fb:request-form', array('action' => 'invite.php',
                                                      'method' => 'post',
                                                      'invite' => 'true',
                                                      'type' => common_config('site', 'name'),
                                                      'content' => $content));
        $this->hidden('invite', 'true');
        // TRANS: %s is the name of the site.
        $actiontext = sprintf(_m('Invite your friends to use %s'), common_config('site', 'name'));

        $multi_params = array('showborder' => 'false');
        $multi_params['actiontext'] = $actiontext;
        $multi_params['bypass'] = 'cancel';
        $multi_params['cols'] = 4;

        // Get a list of users who are already using the app for exclusion
        $exclude_ids = $this->facebook->api_client->friends_getAppUsers();
        $exclude_ids_csv = null;

        // fbml needs these as a csv string, not an array
        if ($exclude_ids) {
            $exclude_ids_csv = implode(',', $exclude_ids);
            $multi_params['exclude_ids'] = $exclude_ids_csv;
        }

        $this->element('fb:multi-friend-selector', $multi_params);
        $this->elementEnd('fb:request-form');

        if ($exclude_ids) {

            // TRANS: %s is the name of the site.
            $this->element('h2', null, sprintf(_m('Friends already using %s:'),
                common_config('site', 'name')));
            $this->elementStart('ul', array('id' => 'facebook-friends'));

            foreach ($exclude_ids as $friend) {
                $this->elementStart('li');
                $this->element('fb:profile-pic', array('uid' => $friend, 'size' => 'square'));
                $this->element('fb:name', array('uid' => $friend,
                                                'capitalize' => 'true'));
                $this->elementEnd('li');
            }

            $this->elementEnd("ul");
        }
    }

    function title()
    {
        // TRANS: Page title.
        return sprintf(_m('Send invitations'));
    }
}

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

        $this->error = $error;

        if ($this->flink) {
            if (!$this->facebook->api_client->users_hasAppPermission('publish_stream') &&
                $this->facebook->api_client->data_getUserPreference(
                     FACEBOOK_PROMPTED_UPDATE_PREF) == 'true') {

                // @todo FIXME: Missing i18n?
                echo '<h1>REDIRECT TO HOME</h1>';
            }
        } else {
            $this->showPage();
        }
    }

    function showContent()
    {
        // If the user has opted not to initially allow the app to have
        // Facebook status update permission, store that preference. Only
        // promt the user the first time she uses the app
        if ($this->arg('skip')) {
            $this->facebook->api_client->data_setUserPreference(
                FACEBOOK_PROMPTED_UPDATE_PREF, 'true');
        }

        if ($this->flink) {
            $this->user = $this->flink->getUser();

            // If this is the first time the user has started the app
             // prompt for Facebook status update permission
             if (!$this->facebook->api_client->users_hasAppPermission('publish_stream')) {

                 if ($this->facebook->api_client->data_getUserPreference(
                         FACEBOOK_PROMPTED_UPDATE_PREF) != 'true') {
                     $this->getUpdatePermission();
                     return;
                 }
             }
        } else {
            $this->showLoginForm();
        }

    }

    function showSuccessContent()
    {

    }

    function showFormContent()
    {

    }

    function title()
    {
        // @todo FIXME: Give a more precise description? Suggestion: "Login with Facebook Connect"
        // TRANS: Page title.
        return sprintf(_m('Login'));
    }

    function redirectHome()
    {

    }
}

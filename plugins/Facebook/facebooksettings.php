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

class FacebooksettingsAction extends FacebookAction
{
    function handle($args)
    {
        parent::handle($args);
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
        if ($this->arg('save')) {
            $this->saveSettings();
        } else {
            $this->showForm();
        }
    }

    function saveSettings() {

        $noticesync = $this->boolean('noticesync');
        $replysync  = $this->boolean('replysync');

        $original = clone($this->flink);
        $this->flink->set_flags($noticesync, false, $replysync, false);
        $result = $this->flink->update($original);

        if ($result === false) {
            $this->showForm(_m('There was a problem saving your sync preferences!'));
        } else {
            // TRANS: Confirmation that synchronisation settings have been saved into the system.
            $this->showForm(_m('Sync preferences saved.'), true);
        }
    }

    function showForm($msg = null, $success = false) {

        if ($msg) {
            if ($success) {
                $this->element('fb:success', array('message' => $msg));
            } else {
                $this->element('fb:error', array('message' => $msg));
            }
        }

        if ($this->facebook->api_client->users_hasAppPermission('publish_stream')) {

            $this->elementStart('form', array('method' => 'post',
                                               'id' => 'facebook_settings'));

            $this->elementStart('ul', 'form_data');

            $this->elementStart('li');

            $this->checkbox('noticesync', _m('Automatically update my Facebook status with my notices.'),
                                ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND) : true);

            $this->elementEnd('li');

            $this->elementStart('li');

            $this->checkbox('replysync', _m('Send "@" replies to Facebook.'),
                             ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true);

            $this->elementEnd('li');

            $this->elementStart('li');

            // TRANS: Submit button to save synchronisation settings.
            $this->submit('save', _m('BUTTON','Save'));

            $this->elementEnd('li');

            $this->elementEnd('ul');

            $this->elementEnd('form');
        } else {
            // TRANS: %s is the application name.
            $instructions = sprintf(_m('If you would like %s to automatically update ' .
                'your Facebook status with your latest notice, you need ' .
                'to give it permission.'), $this->app_name);

            $this->elementStart('p');
            $this->element('span', array('id' => 'permissions_notice'), $instructions);
            $this->elementEnd('p');

            $this->elementStart('ul', array('id' => 'fb-permissions-list'));
            $this->elementStart('li', array('id' => 'fb-permissions-item'));
            $this->elementStart('fb:prompt-permission', array('perms' => 'publish_stream',
                'next_fbjs' => 'document.setLocation(\'' . "$this->app_uri/settings.php" . '\')'));
            $this->element('span', array('class' => 'facebook-button'),
                sprintf(_m('Allow %s to update my Facebook status'), common_config('site', 'name')));
            $this->elementEnd('fb:prompt-permission');
            $this->elementEnd('li');
            $this->elementEnd('ul');
        }
    }

    function title()
    {
        // TRANS: Page title for synchronisation settings.
        return _m('Sync preferences');
    }
}

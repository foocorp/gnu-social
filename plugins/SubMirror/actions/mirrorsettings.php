<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @category  Plugins
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class MirrorSettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */

    function title()
    {
        return _m('Feed mirror settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */

    function getInstructions()
    {
        return _m('You can mirror updates from many RSS and Atom feeds ' .
                  'into your StatusNet timeline!');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $this->showAddFeedForm();
        
        $mirror = new SubMirror();
        $mirror->subscriber = $user->id;
        if ($mirror->find()) {
            while ($mirror->fetch()) {
                $this->showFeedForm($mirror);
            }
        }
    }

    function showFeedForm($mirror)
    {
        $profile = Profile::staticGet('id', $mirror->subscribed);
        if ($profile) {
            $form = new EditMirrorForm($this, $profile);
            $form->show();
        }
    }

    function showAddFeedForm()
    {
        $form = new AddMirrorForm($this);
        $form->show();
    }

    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
     *
     * @return void
     */

    function handlePost()
    {
    }
}

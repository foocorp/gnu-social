<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
 */

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/lib/peopletaglist.php';

/**
 * Key UI methods:
 *
 *  showInputForm() - form asking for a remote profile account or URL
 *                    We end up back here on errors
 *
 *  showPreviewForm() - surrounding form for preview-and-confirm
 *    preview() - display profile for a remote group
 *
 *  success() - redirects to groups page on join
 */
class OStatusPeopletagAction extends OStatusSubAction
{
    protected $profile_uri; // provided acct: or URI of remote entity
    protected $oprofile; // Ostatus_profile of remote entity, if valid


    function validateRemoteProfile()
    {
        if (!$this->oprofile->isPeopletag()) {
            // Send us to the user subscription form for conf
            $target = common_local_url('ostatussub', array(), array('profile' => $this->profile_uri));
            common_redirect($target, 303);
        }
    }

    /**
     * Show the initial form, when we haven't yet been given a valid
     * remote profile.
     */
    function showInputForm()
    {
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_settings',
                                          'action' => $this->selfLink()));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('profile',
                     // TRANS: Field label.
                     _m('Subscribe to list'),
                     $this->profile_uri,
                     // TRANS: Field title.
                     _m("Address of the OStatus list, like http://example.net/user/all/tag."));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        // TRANS: Button text to continue joining a remote list.
        $this->submit('validate', _m('BUTTON','Continue'));

        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Show a preview for a remote peopletag's profile
     * @return boolean true if we're ok to try joining
     */
    function preview()
    {
        $oprofile = $this->oprofile;
        $ptag = $oprofile->localPeopletag();

        $cur = common_current_user();
        if ($ptag->hasSubscriber($cur->id)) {
            $this->element('div', array('class' => 'error'),
                           // TRANS: Error text displayed when trying to subscribe to a list already a subscriber to.
                           _m('You are already subscribed to this list.'));
            $ok = false;
        } else {
            $ok = true;
        }

        $this->showEntity($ptag);
        return $ok;
    }

    function showEntity($ptag)
    {
        $this->elementStart('div', 'peopletag');
        $widget = new PeopletagListItem($ptag, common_current_user(), $this);
        $widget->showCreator();
        $widget->showTag();
        $widget->showDescription();
        $this->elementEnd('div');
    }

    /**
     * Redirect on successful remote people tag subscription
     */
    function success()
    {
        $cur = common_current_user();
        $url = common_local_url('peopletagsubscriptions', array('nickname' => $cur->nickname));
        common_redirect($url, 303);
    }

    /**
     * Attempt to finalize subscription.
     * validateFeed must have been run first.
     *
     * Calls showForm on failure or success on success.
     */
    function saveFeed()
    {
        $user = common_current_user();
        $ptag = $this->oprofile->localPeopletag();
        if ($ptag->hasSubscriber($user->id)) {
            // TRANS: OStatus remote group subscription dialog error.
            $this->showForm(_m('Already subscribed!'));
            return;
        }

        try {
            Profile_tag_subscription::add($ptag, $user);
            $this->success();
        } catch (Exception $e) {
            $this->showForm($e->getMessage());
        }
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Page title for OStatus remote list subscription form
        return _m('Confirm subscription to remote list');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instructions for OStatus list subscription form.
        return _m('You can subscribe to lists from other supported sites. Paste the list\'s URI below:');
    }

    function selfLink()
    {
        return common_local_url('ostatuspeopletag');
    }
}

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

class FacebookhomeAction extends FacebookAction
{
    var $page = null;

    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        // If the user has opted not to initially allow the app to have
        // Facebook status update permission, store that preference. Only
        // promt the user the first time she uses the app
        if ($this->arg('skip') || $args['fb_sig_request_method'] == 'GET') {
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

             // Make sure the user's profile box has the lastest notice
             $notice = $this->user->getCurrentNotice();
             if ($notice) {
                 $this->updateProfileBox($notice);
             }

             if ($this->arg('status_submit') == 'Send') {
                $this->saveNewNotice();
             }

            // User is authenticated and has already been prompted once for
            // Facebook status update permission? Then show the main page
            // of the app
            $this->showPage();
        } else {
            // User hasn't authenticated yet, prompt for creds
            $this->login();
        }
    }

    function login()
    {
        $this->showStylesheets();

        $nickname = common_canonical_nickname($this->trimmed('nickname'));
        $password = $this->arg('password');

        $msg = null;

        if ($nickname) {
            if (common_check_user($nickname, $password)) {
                $user = User::staticGet('nickname', $nickname);

                if (!$user) {
                    $this->showLoginForm(_m("Server error: Couldn't get user!"));
                }

                $flink = DB_DataObject::factory('foreign_link');
                $flink->user_id = $user->id;
                $flink->foreign_id = $this->fbuid;
                $flink->service = FACEBOOK_SERVICE;
                $flink->created = common_sql_now();
                $flink->set_flags(true, false, false, false);

                $flink_id = $flink->insert();

                // XXX: Do some error handling here

                $this->setDefaults();

                $this->getUpdatePermission();
                return;
            } else {
                $msg = _m('Incorrect username or password.');
            }
        }

        $this->showLoginForm($msg);
        $this->showFooter();
    }

    function setDefaults()
    {
        $this->facebook->api_client->data_setUserPreference(
            FACEBOOK_PROMPTED_UPDATE_PREF, 'false');
    }

    function showNoticeForm()
    {
        $post_action = "$this->app_uri/index.php";

        $notice_form = new FacebookNoticeForm($this, $post_action, null,
            $post_action, $this->user);
        $notice_form->show();
    }

    function title()
    {
        if ($this->page > 1) {
            // @todo FIXME: Core should have methods to get "Full name (nickname)" in a localised form
            // so that this can be used consistenly throughout StatusNet without having to implement it
	    // over and over..
            // TRANS: Page title.
            // TRANS: %1$s is a user nickname, %2$s is a page number.
            return sprintf(_m('%1$s and friends, page %2$d'), $this->user->nickname, $this->page);
        } else {
            // TRANS: Page title.
            // TRANS: %s is a user nickname
            return sprintf(_m("%s and friends"), $this->user->nickname);
        }
    }

    function showContent()
    {
        $notice = $this->user->noticeInbox(($this->page-1) * NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'index.php', array('nickname' => $this->user->nickname));
    }

    function showNoticeList($notice)
    {
        $nl = new NoticeList($notice, $this);
        return $nl->show();
    }

    function getUpdatePermission() {
        $this->showStylesheets();

        $this->elementStart('div', array('class' => 'facebook_guide'));

        // TRANS: Instructions. %s is the application name.
        $instructions = sprintf(_m('If you would like the %s app to automatically update ' .
            'your Facebook status with your latest notice, you need ' .
            'to give it permission.'), $this->app_name);

        $this->elementStart('p');
        $this->element('span', array('id' => 'permissions_notice'), $instructions);
        $this->elementEnd('p');

        $this->elementStart('form', array('method' => 'post',
                                           'action' => "index.php",
                                           'id' => 'facebook-skip-permissions'));

        $this->elementStart('ul', array('id' => 'fb-permissions-list'));
        $this->elementStart('li', array('id' => 'fb-permissions-item'));

        $next = urlencode("$this->app_uri/index.php");
        $api_key = common_config('facebook', 'apikey');

        $auth_url = 'http://www.facebook.com/authorize.php?api_key=' .
            $api_key . '&v=1.0&ext_perm=publish_stream&next=' . $next .
            '&next_cancel=' . $next . '&submit=skip';

        $this->elementStart('span', array('class' => 'facebook-button'));
        // @todo FIXME: sprintf not needed here?
        $this->element('a', array('href' => $auth_url),
            sprintf(_m('Okay, do it!'), $this->app_name));
        $this->elementEnd('span');

        $this->elementEnd('li');

        $this->elementStart('li', array('id' => 'fb-permissions-item'));
        // TRANS: Button text. Clicking the button will skip updating Facebook permissions.
        $this->submit('skip', _m('BUTTON','Skip'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementEnd('form');
        $this->elementEnd('div');
    }

    /**
     * Generate pagination links
     *
     * @param boolean $have_before is there something before?
     * @param boolean $have_after  is there something after?
     * @param integer $page        current page
     * @param string  $action      current action
     * @param array   $args        rest of query arguments
     *
     * @return nothing
     */
    function pagination($have_before, $have_after, $page, $action, $args=null)
    {
        // Does a little before-after block for next/prev page

        // XXX: Fix so this uses common_local_url() if possible.

        if ($have_before || $have_after) {
            $this->elementStart('dl', 'pagination');
            $this->element('dt', null, _m('Pagination'));
            $this->elementStart('dd', null);
            $this->elementStart('ul', array('class' => 'nav'));
        }
        if ($have_before) {
            $pargs   = array('page' => $page-1);
            $newargs = $args ? array_merge($args, $pargs) : $pargs;
            $this->elementStart('li', array('class' => 'nav_prev'));
            $this->element('a', array('href' => "$action?page=$newargs[page]", 'rel' => 'prev'),
                           // TRANS: Pagination link.
                           _m('After'));
            $this->elementEnd('li');
        }
        if ($have_after) {
            $pargs   = array('page' => $page+1);
            $newargs = $args ? array_merge($args, $pargs) : $pargs;
            $this->elementStart('li', array('class' => 'nav_next'));
            $this->element('a', array('href' => "$action?page=$newargs[page]", 'rel' => 'next'),
                           // TRANS: Pagination link.
                           _m('Before'));
            $this->elementEnd('li');
        }
        if ($have_before || $have_after) {
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
    }
}

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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

class OpenidloginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);
        if (common_is_real_login()) {
            // TRANS: Client error message trying to log on with OpenID while already logged on.
            $this->clientError(_m('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $provider = common_config('openid', 'trusted_provider');
            if ($provider) {
                $openid_url = $provider;
                if (common_config('openid', 'append_username')) {
                    $openid_url .= $this->trimmed('openid_username');
                }
            } else {
                $openid_url = $this->trimmed('openid_url');
            }

            oid_assert_allowed($openid_url);

            $rememberme = $this->boolean('rememberme');

            common_ensure_session();

            $_SESSION['openid_rememberme'] = $rememberme;

            $result = oid_authenticate($openid_url,
                                       'finishopenidlogin');

            if (is_string($result)) { # error message
                unset($_SESSION['openid_rememberme']);
                $this->showForm($result, $openid_url);
            }
        } else {
            $openid_url = oid_get_last();
            $this->showForm(null, $openid_url);
        }
    }

    function getInstructions()
    {
        if (common_logged_in() && !common_is_real_login() &&
            common_get_returnto()) {
            // rememberme logins have to reauthenticate before
            // changing any profile settings (cookie-stealing protection)
            // TRANS: OpenID plugin message. Rememberme logins have to reauthenticate before changing any profile settings.
            // TRANS: "OpenID" is the display text for a link with URL "(%%doc.openid%%)".
            return _m('For security reasons, please re-login with your ' .
                     '[OpenID](%%doc.openid%%) ' .
                     'before changing your settings.');
        } else {
            // TRANS: OpenID plugin message.
            // TRANS: "OpenID" is the display text for a link with URL "(%%doc.openid%%)".
            return _m('Login with an [OpenID](%%doc.openid%%) account.');
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $instr = $this->getInstructions();
            $output = common_markup_to_html($instr);
            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    function showScripts()
    {
        parent::showScripts();
        if (common_config('openid', 'trusted_provider')) {
            if (common_config('openid', 'append_username')) {
                $this->autofocus('openid_username');
            } else {
                $this->autofocus('rememberme');
            }
        } else {
            $this->autofocus('openid_url');
        }
    }

    function title()
    {
        // TRANS: OpenID plugin message. Title.
        return _m('TITLE','OpenID Login');
    }

    function showForm($error=null, $openid_url)
    {
        $this->error = $error;
        $this->openid_url = $openid_url;
        $this->showPage();
    }

    function showContent() {
        $formaction = common_local_url('openidlogin');
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_openid_login',
                                           'class' => 'form_settings',
                                           'action' => $formaction));
        $this->elementStart('fieldset');
        // TRANS: OpenID plugin logon form legend.
        $this->element('legend', null, _m('LEGEND','OpenID login'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $provider = common_config('openid', 'trusted_provider');
        $appendUsername = common_config('openid', 'append_username');
        if ($provider) {
            // TRANS: Field label.
            $this->element('label', array(), _m('LABEL','OpenID provider'));
            $this->element('span', array(), $provider);
            if ($appendUsername) {
                $this->element('input', array('id' => 'openid_username',
                                              'name' => 'openid_username',
                                              'style' => 'float: none'));
            }
            $this->element('p', 'form_guide',
                           // TRANS: Form guide.
                           ($appendUsername ? _m('Enter your username.') . ' ' : '') .
                           // TRANS: Form guide.
                           _m('You will be sent to the provider\'s site for authentication.'));
            $this->hidden('openid_url', $provider);
        } else {
            // TRANS: OpenID plugin logon form field label.
            $this->input('openid_url', _m('OpenID URL'),
                         $this->openid_url,
                        // TRANS: OpenID plugin logon form field title.
                         _m('Your OpenID URL.'));
        }
        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'settings_rememberme'));
        // TRANS: OpenID plugin logon form checkbox label for setting to put the OpenID information in a cookie.
        $this->checkbox('rememberme', _m('Remember me'), false,
                        // TRANS: OpenID plugin logon form field title.
                        _m('Automatically login in the future; ' .
                           'not for shared computers!'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: OpenID plugin logon form button label to start logon with the data provided in the logon form.
        $this->submit('submit', _m('BUTTON', 'Login'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }

    function showNoticeForm()
    {
    }

    function showProfileBlock()
    {
    }
}

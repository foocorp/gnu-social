<?php
/*
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
 */

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }


class OStatusTagAction extends OStatusInitAction
{
    var $nickname;
    var $profile;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            // TRANS: Client error displayed when trying to list a local object as if it is remote.
            $this->clientError(_m('You can use the local list functionality!'));
            return false;
        }

        $this->nickname = $this->trimmed('nickname');

        // Webfinger or profile URL of the remote user
        $this->profile = $this->trimmed('profile');

        return true;
    }

    function showContent()
    {
        // TRANS: Header for listing a remote object. %s is a remote object's name.
        $header = sprintf(_m('List %s'), $this->nickname);
        // TRANS: Button text to list a remote object.
        $submit = _m('BUTTON','Go');
        $this->elementStart('form', array('id' => 'form_ostatus_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('ostatustag')));
        $this->elementStart('fieldset');
        $this->element('legend', null,  $header);
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'ostatus_nickname'));
        // TRANS: Field label.
        $this->input('nickname', _m('User nickname'), $this->nickname,
                     // TRANS: Field title.
                     _m('Nickname of the user you want to list.'));
        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'ostatus_profile'));
        // TRANS: Field label.
        $this->input('profile', _m('Profile Account'), $this->profile,
                     // TRANS: Field title.
                     _m('Your account id (for example user@identi.ca).'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', $submit);
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function connectWebfinger($acct)
    {
        $target_profile = $this->targetProfile();

        $disco = new Discovery;
        $xrd = $disco->lookup($acct);

        $link = $xrd->get('http://ostatus.org/schema/1.0/tag');
        if (!is_null($link)) {
            // We found a URL - let's redirect!
            if (!empty($link->template)) {
                $url = Discovery::applyTemplate($link->template, $target_profile);
            } else {
                $url = $link->href;
            }
            common_log(LOG_INFO, "Sending remote subscriber $acct to $url");
            common_redirect($url, 303);
        }
        // TRANS: Client error displayed when remote profile address could not be confirmed.
        $this->clientError(_m('Could not confirm remote profile address.'));
    }

    function connectProfile($subscriber_profile)
    {
        $target_profile = $this->targetProfile();

        // @fixme hack hack! We should look up the remote sub URL from XRDS
        $suburl = preg_replace('!^(.*)/(.*?)$!', '$1/main/tagprofile', $subscriber_profile);
        $suburl .= '?uri=' . urlencode($target_profile);

        common_log(LOG_INFO, "Sending remote subscriber $subscriber_profile to $suburl");
        common_redirect($suburl, 303);
    }

    function title()
    {
      // TRANS: Title for an OStatus list.
      return _m('OStatus list');  
    }
}

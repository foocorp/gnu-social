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


class OStatusInitAction extends Action
{

    var $nickname;
    var $group;
    var $profile;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            $this->clientError(_m('You can use the local subscription!'));
            return false;
        }

        // Local user or group the remote wants to subscribe to
        $this->nickname = $this->trimmed('nickname');
        $this->group = $this->trimmed('group');
        
        // Webfinger or profile URL of the remote user
        $this->profile = $this->trimmed('profile');

        return true;
    }
    
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            /* Use a session token for CSRF protection. */
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_m('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }
            $this->ostatusConnect();
        } else {
            $this->showForm();
        }
    }
    
    function showForm($err = null)
    {
        $this->err = $err;
        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            $this->element('title', null, _m('Subscribe to user'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showContent();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    function showContent()
    {

      $this->elementStart('p',_m("With GNU social, users do not use one server to communicate in the way that Facebook and Twitter users do. Instead, users are spread out over a network of servers and different sites. You can run your own server, or you can sign up for one of the public servers &mdash; it doesn't even need to be a GNU social server &mdash; any server that speaks the OStatus protocol is suitable. A good place to get an account for yourself is www.status.net"));
      $this->elementEnd('p');
    
        if ($this->group) {
            $header = sprintf(_m('Join group %s'), $this->group);
            $submit = _m('Join');
        } else {
            $header = sprintf(_m('Subscribe to %s'), $this->nickname);
            $submit = _m('Subscribe');
        }
        $this->elementStart('form', array('id' => 'form_ostatus_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('ostatusinit')));
        $this->elementStart('fieldset');
        $this->element('legend', null,  $header);
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'ostatus_nickname'));
        $this->input('nickname', _m('User nickname'), $this->nickname,
                     _m('Nickname of the user you want to follow'));
        $this->hidden('group', $this->group); // pass-through for magic links
        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'ostatus_profile'));
        $this->input('profile', _m('Profile Account'), $this->profile,
                     _m('Your account id (i.e. user@identi.ca)'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', $submit);
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function ostatusConnect()
    {
        $opts = array('allowed_schemes' => array('http', 'https', 'acct'));
        if (Validate::uri($this->profile, $opts)) {
            $bits = parse_url($this->profile);
            if ($bits['scheme'] == 'acct') {
                $this->connectWebfinger($bits['path']);
            } else {
                $this->connectProfile($this->profile);
            }
        } elseif (strpos($this->profile, '@') !== false) {
            $this->connectWebfinger($this->profile);
        } else {
            $this->clientError(_m("Must provide a remote profile."));
        }
    }

    function connectWebfinger($acct)
    {
        $target_profile = $this->targetProfile();

        $disco = new Discovery;
        $result = $disco->lookup($acct);
        if (!$result) {
            $this->clientError(_m("Couldn't look up OStatus account profile."));
        }

        foreach ($result->links as $link) {
            if ($link['rel'] == 'http://ostatus.org/schema/1.0/subscribe') {
                // We found a URL - let's redirect!
                $url = Discovery::applyTemplate($link['template'], $target_profile);
                common_log(LOG_INFO, "Sending remote subscriber $acct to $url");
                common_redirect($url, 303);
            }

        }
        $this->clientError(_m("Couldn't confirm remote profile address."));
    }

    function connectProfile($subscriber_profile)
    {
        $target_profile = $this->targetProfile();

        // @fixme hack hack! We should look up the remote sub URL from XRDS
        $suburl = preg_replace('!^(.*)/(.*?)$!', '$1/main/ostatussub', $subscriber_profile);
        $suburl .= '?profile=' . urlencode($target_profile);

        common_log(LOG_INFO, "Sending remote subscriber $subscriber_profile to $suburl");
        common_redirect($suburl, 303);
    }

    /**
     * Build the canonical profile URI+URL of the requested user or group
     */
    function targetProfile()
    {
        if ($this->nickname) {
            $user = User::staticGet('nickname', $this->nickname);
            if ($user) {
                return common_local_url('userbyid', array('id' => $user->id));
            } else {
                $this->clientError("No such user.");
            }
        } else if ($this->group) {
            $group = Local_group::staticGet('nickname', $this->group);
            if ($group) {
                return common_local_url('groupbyid', array('id' => $group->group_id));
            } else {
                $this->clientError("No such group.");
            }
        } else {
            $this->clientError("No local user or group nickname provided.");
        }
    }

    function title()
    {
      return _m('OStatus Connect');  
    }

  
  
}

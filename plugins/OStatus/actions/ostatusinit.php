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

if (!defined('STATUSNET')) {
    exit(1);
}

class OStatusInitAction extends Action
{
    var $nickname;
    var $tagger;
    var $peopletag;
    var $group;
    var $profile;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            // TRANS: Client error.
            $this->clientError(_m('You can use the local subscription!'));
            return false;
        }

        // Local user or group the remote wants to subscribe to
        $this->nickname = $this->trimmed('nickname');
        $this->tagger = $this->trimmed('tagger');
        $this->peopletag = $this->trimmed('peopletag');
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
                // TRANS: Client error displayed when the session token does not match or is not given.
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
            // TRANS: Form title.
            $this->element('title', null, _m('TITLE','Subscribe to user'));
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
    
        if ($this->group) {
            // TRANS: Form legend. %s is a group name.
            $header = sprintf(_m('Join group %s'), $this->group);
            // TRANS: Button text to join a group.
            $submit = _m('BUTTON','Join');
        } else if ($this->peopletag && $this->tagger) {
            // TRANS: Form legend. %1$s is a list, %2$s is a lister's name.
            $header = sprintf(_m('Subscribe to list %1$s by %2$s'), $this->peopletag, $this->tagger);
            // TRANS: Button text to subscribe to a list.
            $submit = _m('BUTTON','Subscribe');
            // TRANS: Button text.
        } else {
            // TRANS: Form legend. %s is a nickname.
            $header = sprintf(_m('Subscribe to %s'), $this->nickname);
            // TRANS: Button text to subscribe to a profile.
            $submit = _m('BUTTON','Subscribe');
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

        if ($this->group) {
            // TRANS: Field label.
            $this->input('group', _m('Group nickname'), $this->group,
                         // TRANS: Field title.
                         _m('Nickname of the group you want to join.'));
        } else {
            // TRANS: Field label.
            $this->input('nickname', _m('User nickname'), $this->nickname,
                         // TRANS: Field title.
                         _m('Nickname of the user you want to follow.'));
            $this->hidden('tagger', $this->tagger);
            $this->hidden('peopletag', $this->peopletag);
        }

        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'ostatus_profile'));
        // TRANS: Field label.
        $this->input('profile', _m('Profile Account'), $this->profile,
                      // TRANS: Tooltip for field label "Profile Account".
                     _m('Your account ID (e.g. user@identi.ca).'));
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
            // TRANS: Client error.
            $this->clientError(_m('Must provide a remote profile.'));
        }
    }

    function connectWebfinger($acct)
    {
        $target_profile = $this->targetProfile();

        $disco = new Discovery;
        $result = $disco->lookup($acct);
        if (!$result) {
            // TRANS: Client error.
            $this->clientError(_m('Could not look up OStatus account profile.'));
        }

        foreach ($result->links as $link) {
            if ($link['rel'] == 'http://ostatus.org/schema/1.0/subscribe') {
                // We found a URL - let's redirect!
                $url = Discovery::applyTemplate($link['template'], $target_profile);
                common_log(LOG_INFO, "Sending remote subscriber $acct to $url");
                common_redirect($url, 303);
            }

        }
        // TRANS: Client error.
        $this->clientError(_m('Could not confirm remote profile address.'));
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
                // TRANS: Client error.
                $this->clientError(_m('No such user.'));
            }
        } else if ($this->group) {
            $group = Local_group::staticGet('nickname', $this->group);
            if ($group) {
                return common_local_url('groupbyid', array('id' => $group->group_id));
            } else {
                // TRANS: Client error.
                $this->clientError(_m('No such group.'));
            }
        } else if ($this->peopletag && $this->tagger) {
            $user = User::staticGet('nickname', $this->tagger);
            if (empty($user)) {
                // TRANS: Client error.
                $this->clientError(_m('No such user.'));
            }

            $peopletag = Profile_list::getByTaggerAndTag($user->id, $this->peopletag);
            if ($peopletag) {
                return common_local_url('profiletagbyid',
                    array('tagger_id' => $user->id, 'id' => $peopletag->id));
            }
            // TRANS: Client error.
            $this->clientError(_m('No such list.'));
        } else {
            // TRANS: Client error.
            $this->clientError(_m('No local user or group nickname provided.'));
        }
    }

    function title()
    {
      // TRANS: Page title.
      return _m('OStatus Connect');
    }
}

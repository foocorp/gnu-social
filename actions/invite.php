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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class InviteAction extends CurrentUserDesignAction
{
    var $mode = null;
    var $error = null;
    var $already = null;
    var $subbed = null;
    var $sent = null;

    function isReadOnly($args)
    {
        return false;
    }

    function handle($args)
    {
        parent::handle($args);
        if (!common_config('invite', 'enabled')) {
            $this->clientError(_('Invites have been disabled.'));
        } else if (!common_logged_in()) {
            $this->clientError(sprintf(_('You must be logged in to invite other users to use %s.'),
                                        common_config('site', 'name')));
            return;
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->sendInvitations();
        } else {
            $this->showForm();
        }
    }

    function sendInvitations()
    {
        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $user = common_current_user();
        $profile = $user->getProfile();

        $bestname = $profile->getBestName();
        $sitename = common_config('site', 'name');
        $personal = $this->trimmed('personal');

        $addresses = explode("\n", $this->trimmed('addresses'));

        foreach ($addresses as $email) {
            $email = trim($email);
            if (!Validate::email($email, common_config('email', 'check_domain'))) {
                $this->showForm(sprintf(_('Invalid email address: %s'), $email));
                return;
            }
        }

        $this->already = array();
        $this->subbed = array();

        foreach ($addresses as $email) {
            $email = common_canonical_email($email);
            $other = User::staticGet('email', $email);
            if ($other) {
                if ($user->isSubscribed($other)) {
                    $this->already[] = $other;
                } else {
                    subs_subscribe_to($user, $other);
                    $this->subbed[] = $other;
                }
            } else {
                $this->sent[] = $email;
                $this->sendInvitation($email, $user, $personal);
            }
        }

        $this->mode = 'sent';

        $this->showPage();
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('addresses');
    }

    function title()
    {
        if ($this->mode == 'sent') {
            return _('Invitation(s) sent');
        } else {
            return _('Invite new users');
        }
    }

    function showContent()
    {
        if ($this->mode == 'sent') {
            $this->showInvitationSuccess();
        } else {
            $this->showInviteForm();
        }
    }

    function showInvitationSuccess()
    {
        if ($this->already) {
            $this->element('p', null, _('You are already subscribed to these users:'));
            $this->elementStart('ul');
            foreach ($this->already as $other) {
                $this->element('li', null, sprintf(_('%1$s (%2$s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($this->subbed) {
            $this->element('p', null, _('These people are already users and you were automatically subscribed to them:'));
            $this->elementStart('ul');
            foreach ($this->subbed as $other) {
                $this->element('li', null, sprintf(_('%1$s (%2$s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($this->sent) {
            $this->element('p', null, _('Invitation(s) sent to the following people:'));
            $this->elementStart('ul');
            foreach ($this->sent as $other) {
                $this->element('li', null, $other);
            }
            $this->elementEnd('ul');
            $this->element('p', null, _('You will be notified when your invitees accept the invitation and register on the site. Thanks for growing the community!'));
        }
    }

    function showPageNotice()
    {
        if ($this->mode != 'sent') {
            if ($this->error) {
                $this->element('p', 'error', $this->error);
            } else {
                $this->elementStart('div', 'instructions');
                $this->element('p', null,
                               _('Use this form to invite your friends and colleagues to use this service.'));
                $this->elementEnd('div');
            }
        }
    }

    function showForm($error=null)
    {
        $this->mode = 'form';
        $this->error = $error;
        $this->showPage();
    }

    function showInviteForm()
    {
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_invite',
                                           'class' => 'form_settings',
                                           'action' => common_local_url('invite')));
        $this->elementStart('fieldset');
        $this->element('legend', null, 'Send an invitation');
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->textarea('addresses', _('Email addresses'),
                        $this->trimmed('addresses'),
                        _('Addresses of friends to invite (one per line)'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->textarea('personal', _('Personal message'),
                        $this->trimmed('personal'),
                        _('Optionally add a personal message to the invitation.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Send button for inviting friends
        $this->submit('send', _m('BUTTON', 'Send'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function sendInvitation($email, $user, $personal)
    {
        $profile = $user->getProfile();
        $bestname = $profile->getBestName();

        $sitename = common_config('site', 'name');

        $invite = new Invitation();

        $invite->address = $email;
        $invite->address_type = 'email';
        $invite->code = common_confirmation_code(128);
        $invite->user_id = $user->id;
        $invite->created = common_sql_now();

        if (!$invite->insert()) {
            common_log_db_error($invite, 'INSERT', __FILE__);
            return false;
        }

        $recipients = array($email);

        $headers['From'] = mail_notify_from();
        $headers['To'] = trim($email);
        // TRANS: Subject for invitation email. Note that 'them' is correct as a gender-neutral singular 3rd-person pronoun in English.
        $headers['Subject'] = sprintf(_('%1$s has invited you to join them on %2$s'), $bestname, $sitename);

        // TRANS: Body text for invitation email. Note that 'them' is correct as a gender-neutral singular 3rd-person pronoun in English.
        $body = sprintf(_("%1\$s has invited you to join them on %2\$s (%3\$s).\n\n".
                          "%2\$s is a micro-blogging service that lets you keep up-to-date with people you know and people who interest you.\n\n".
                          "You can also share news about yourself, your thoughts, or your life online with people who know about you. ".
                          "It's also great for meeting new people who share your interests.\n\n".
                          "%1\$s said:\n\n%4\$s\n\n".
                          "You can see %1\$s's profile page on %2\$s here:\n\n".
                          "%5\$s\n\n".
                          "If you'd like to try the service, click on the link below to accept the invitation.\n\n".
                          "%6\$s\n\n".
                          "If not, you can ignore this message. Thanks for your patience and your time.\n\n".
                          "Sincerely, %2\$s\n"),
                        $bestname,
                        $sitename,
                        common_root_url(),
                        $personal,
                        common_local_url('showstream', array('nickname' => $user->nickname)),
                        common_local_url('register', array('code' => $invite->code)));

        mail_send($recipients, $headers, $body);
    }

    function showLocalNav()
    {
        $nav = new SubGroupNav($this, common_current_user());
        $nav->show();
    }
}

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
            // TRANS: Client error displayed when trying to sent invites while they have been disabled.
            $this->clientError(_('Invites have been disabled.'));
        } else if (!common_logged_in()) {
            // TRANS: Client error displayed when trying to sent invites while not logged in.
            // TRANS: %s is the StatusNet site name.
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
                // TRANS: Form validation message when providing an e-mail address that does not validate.
                // TRANS: %s is an invalid e-mail address.
                $this->showForm(sprintf(_('Invalid email address: %s.'), $email));
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
            // TRANS: Page title when invitations have been sent.
            return _('Invitations sent');
        } else {
            // TRANS: Page title when inviting potential users.
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
            // TRANS: Message displayed inviting users to use a StatusNet site while the inviting user
            // TRANS: is already subscribed to one or more users with the given e-mail address(es).
            // TRANS: Plural form is based on the number of reported already subscribed e-mail addresses.
            // TRANS: Followed by a bullet list.
            $this->element('p', null, _m('You are already subscribed to this user:',
                                         'You are already subscribed to these users:',
                                         count($this->already)));
            $this->elementStart('ul');
            foreach ($this->already as $other) {
                // TRANS: Used as list item for already subscribed users (%1$s is nickname, %2$s is e-mail address).
                $this->element('li', null, sprintf(_m('INVITE','%1$s (%2$s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($this->subbed) {
            // TRANS: Message displayed inviting users to use a StatusNet site while the invited user
            // TRANS: already uses a this StatusNet site. Plural form is based on the number of
            // TRANS: reported already present people. Followed by a bullet list.
            $this->element('p', null, _m('This person is already a user and you were automatically subscribed:',
                                         'These people are already users and you were automatically subscribed to them:',
                                         count($this->subbed)));
            $this->elementStart('ul');
            foreach ($this->subbed as $other) {
                // TRANS: Used as list item for already registered people (%1$s is nickname, %2$s is e-mail address).
                $this->element('li', null, sprintf(_m('INVITE','%1$s (%2$s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($this->sent) {
            // TRANS: Message displayed inviting users to use a StatusNet site. Plural form is
            // TRANS: based on the number of invitations sent. Followed by a bullet list of
            // TRANS: e-mail addresses to which invitations were sent.
            $this->element('p', null, _m('Invitation sent to the following person:',
                                         'Invitations sent to the following people:',
                                         count($this->sent)));
            $this->elementStart('ul');
            foreach ($this->sent as $other) {
                $this->element('li', null, $other);
            }
            $this->elementEnd('ul');
            // TRANS: Generic message displayed after sending out one or more invitations to
            // TRANS: people to join a StatusNet site.
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
                               // TRANS: Form instructions.
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
        // TRANS: Form legend.
        $this->element('legend', null, 'Send an invitation');
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label for a list of e-mail addresses.
        $this->textarea('addresses', _('Email addresses'),
                        $this->trimmed('addresses'),
                        // TRANS: Tooltip for field label for a list of e-mail addresses.
                        _('Addresses of friends to invite (one per line)'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label for a personal message to send to invitees.
        $this->textarea('personal', _('Personal message'),
                        $this->trimmed('personal'),
                        // TRANS: Tooltip for field label for a personal message to send to invitees.
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
        // TRANS: Subject for invitation email. Note that 'them' is correct as a gender-neutral
        // TRANS: singular 3rd-person pronoun in English. %1$s is the inviting user, $2$s is
        // TRANS: the StatusNet sitename.
        $headers['Subject'] = sprintf(_('%1$s has invited you to join them on %2$s'), $bestname, $sitename);

        // TRANS: Body text for invitation email. Note that 'them' is correct as a gender-neutral
        // TRANS: singular 3rd-person pronoun in English. %1$s is the inviting user, %2$s is the
        // TRANS: StatusNet sitename, %3$s is the site URL, %4$s is the personal message from the
        // TRANS: inviting user, %s%5 a link to the timeline for the inviting user, %s$6 is a link
        // TRANS: to register with the StatusNet site.
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

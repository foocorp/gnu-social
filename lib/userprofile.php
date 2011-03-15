<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Profile for a particular user
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Profile of a user
 *
 * Shows profile information about a particular user
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */
class UserProfile extends Widget
{
    var $user = null;
    var $profile = null;

    function __construct($action=null, $user=null, $profile=null)
    {
        parent::__construct($action);
        $this->user = $user;
        $this->profile = $profile;
    }

    function show()
    {
        $this->showProfileData();
        $this->showEntityActions();
    }

    function showProfileData()
    {
        if (Event::handle('StartProfilePageProfileSection', array(&$this->out, $this->profile))) {

            $this->out->elementStart('div', array('id' => 'i',
                                                  'class' => 'entity_profile vcard author'));
            // TRANS: H2 for user profile information.
            $this->out->element('h2', null, _('User profile'));

            if (Event::handle('StartProfilePageProfileElements', array(&$this->out, $this->profile))) {

                $this->showAvatar();
                $this->showNickname();
                $this->showFullName();
                $this->showLocation();
                $this->showHomepage();
                $this->showBio();
                $this->showProfileTags();

                Event::handle('EndProfilePageProfileElements', array(&$this->out, $this->profile));
            }

            $this->out->elementEnd('div');
            Event::handle('EndProfilePageProfileSection', array(&$this->out, $this->profile));
        }
    }

    function showAvatar()
    {
        $this->out->elementStart('div', 'ur_face');

        if (Event::handle('StartProfilePageAvatar', array($this->out, $this->profile))) {

            $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
            if (!$avatar) {
                // hack for remote Twitter users: no 96px, but large Twitter size is 73px
                $avatar = $this->profile->getAvatar(73);
            }

            $this->out->element('img', 
                                array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE),
                                      'class' => 'photo avatar entity_depiction',
                                      'width' => AVATAR_PROFILE_SIZE,
                                      'height' => AVATAR_PROFILE_SIZE,
                                      'alt' => $this->profile->nickname));
            
            $cur = common_current_user();

            if ($cur && $cur->id == $this->profile->id) {
                $this->out->element('a', array('href' => common_local_url('avatarsettings')), _('Edit Avatar'));
            }

            Event::handle('EndProfilePageAvatar',
                          array($this->out, $this->profile));
        }

        $this->out->elementEnd('div');
    }

    function showNickname()
    {
        if (Event::handle('StartProfilePageNickname', array($this->out, $this->profile))) {

            $hasFN = ($this->profile->fullname) ? 'entity_nickname nickname url uid' : 'entity_nickname fn nickname url uid';
            $this->out->element('a',
                                array('href' => $this->profile->profileurl,
                                      'rel' => 'me',
                                      'class' => $hasFN),
                           $this->profile->nickname);

            Event::handle('EndProfilePageNickname', array($this->out, $this->profile));
        }
    }

    function showFullName()
    {
        if (Event::handle('StartProfilePageFullName', array($this->out, $this->profile))) {
            if ($this->profile->fullname) {
                $this->out->element('span',
                                    'entity_fn fn',
                                    $this->profile->fullname);
            }
            Event::handle('EndProfilePageFullName', array($this->out, $this->profile));
        }
    }

    function showLocation()
    {
        if (Event::handle('StartProfilePageLocation', array($this->out, $this->profile))) {
            if ($this->profile->location) {
                $this->out->element('span',
                                    'entity_location label',
                                    $this->profile->location);
            }
            Event::handle('EndProfilePageLocation', array($this->out, $this->profile));
        }
    }

    function showHomepage()
    {
        if (Event::handle('StartProfilePageHomepage', array($this->out, $this->profile))) {
            if ($this->profile->homepage) {
                $this->out->element('a',
                                    array('href' => $this->profile->homepage,
                                          'rel' => 'me',
                                          'class' => 'url entity_url'),
                               $this->profile->homepage);
            }
            Event::handle('EndProfilePageHomepage', array($this->out, $this->profile));
        }
    }

    function showBio()
    {
        if (Event::handle('StartProfilePageBio', array($this->out, $this->profile))) {
            if ($this->profile->bio) {
                $this->out->element('div',
                                    'note entity_note',
                                    $this->profile->bio);
            }
            Event::handle('EndProfilePageBio', array($this->out, $this->profile));
        }
    }

    function showProfileTags()
    {
        if (Event::handle('StartProfilePageProfileTags', array($this->out, $this->profile))) {
            $tags = Profile_tag::getTags($this->profile->id, $this->profile->id);

            if (count($tags) > 0) {
                $this->out->elementStart('ul', 'tags xoxo entity_tags');
                foreach ($tags as $tag) {
                    $this->out->elementStart('li');
                    // Avoid space by using raw output.
                    $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
                      common_local_url('peopletag', array('tag' => $tag)) .
                      '">' . $tag . '</a>';
                    $this->out->raw($pt);
                    $this->out->elementEnd('li');
                }
                $this->out->elementEnd('ul');
            }
            Event::handle('EndProfilePageProfileTags', array($this->out, $this->profile));
        }
    }

    function showEntityActions()
    {
        if ($this->profile->hasRole(Profile_role::DELETED)) {
            $this->out->elementStart('div', 'entity_actions');
            // TRANS: H2 for user actions in a profile.
            $this->out->element('h2', null, _('User actions'));
            $this->out->elementStart('ul');
            $this->out->elementStart('p', array('class' => 'profile_deleted'));
            // TRANS: Text shown in user profile of not yet compeltely deleted users.
            $this->out->text(_('User deletion in progress...'));
            $this->out->elementEnd('p');
            $this->out->elementEnd('ul');
            $this->out->elementEnd('div');
            return;
        }
        if (Event::handle('StartProfilePageActionsSection', array($this->out, $this->profile))) {

            $cur = common_current_user();

            $this->out->elementStart('div', 'entity_actions');
            // TRANS: H2 for entity actions in a profile.
            $this->out->element('h2', null, _('User actions'));
            $this->out->elementStart('ul');

            if (Event::handle('StartProfilePageActionsElements', array($this->out, $this->profile))) {
                if (empty($cur)) { // not logged in
                    if (Event::handle('StartProfileRemoteSubscribe', array($this->out, $this->profile))) {
                        $this->out->elementStart('li', 'entity_subscribe');
                        $this->showRemoteSubscribeLink();
                        $this->out->elementEnd('li');
                        Event::handle('EndProfileRemoteSubscribe', array($this->out, $this->profile));
                    }
                } else {
                    if ($cur->id == $this->profile->id) { // your own page
                        $this->out->elementStart('li', 'entity_edit');
                        $this->out->element('a', array('href' => common_local_url('profilesettings'),
                                                  // TRANS: Link title for link on user profile.
                                                  'title' => _('Edit profile settings')),
                                       // TRANS: Link text for link on user profile.
                                       _('Edit'));
                        $this->out->elementEnd('li');
                    } else { // someone else's page

                        // subscribe/unsubscribe button

                        $this->out->elementStart('li', 'entity_subscribe');

                        if ($cur->isSubscribed($this->profile)) {
                            $usf = new UnsubscribeForm($this->out, $this->profile);
                            $usf->show();
                        } else {
                            $sf = new SubscribeForm($this->out, $this->profile);
                            $sf->show();
                        }
                        $this->out->elementEnd('li');

                        if ($cur->mutuallySubscribed($this->profile)) {

                            // message

                            $this->out->elementStart('li', 'entity_send-a-message');
                            $this->out->element('a', array('href' => common_local_url('newmessage', array('to' => $this->user->id)),
                                                      // TRANS: Link title for link on user profile.
                                                      'title' => _('Send a direct message to this user')),
                                           // TRANS: Link text for link on user profile.
                                           _('Message'));
                            $this->out->elementEnd('li');

                            // nudge

                            if ($this->user && $this->user->email && $this->user->emailnotifynudge) {
                                $this->out->elementStart('li', 'entity_nudge');
                                $nf = new NudgeForm($this->out, $this->user);
                                $nf->show();
                                $this->out->elementEnd('li');
                            }
                        }

                        // return-to args, so we don't have to keep re-writing them

                        list($action, $r2args) = $this->out->returnToArgs();

                        // push the action into the list

                        $r2args['action'] = $action;

                        // block/unblock

                        $blocked = $cur->hasBlocked($this->profile);
                        $this->out->elementStart('li', 'entity_block');
                        if ($blocked) {
                            $ubf = new UnblockForm($this->out, $this->profile, $r2args);
                            $ubf->show();
                        } else {
                            $bf = new BlockForm($this->out, $this->profile, $r2args);
                            $bf->show();
                        }
                        $this->out->elementEnd('li');

                        // Some actions won't be applicable to non-local users.
                        $isLocal = !empty($this->user);

                        if ($cur->hasRight(Right::SANDBOXUSER) ||
                            $cur->hasRight(Right::SILENCEUSER) ||
                            $cur->hasRight(Right::DELETEUSER)) {
                            $this->out->elementStart('li', 'entity_moderation');
                            // TRANS: Label text on user profile to select a user role.
                            $this->out->element('p', null, _('Moderate'));
                            $this->out->elementStart('ul');
                            if ($cur->hasRight(Right::SANDBOXUSER)) {
                                $this->out->elementStart('li', 'entity_sandbox');
                                if ($this->profile->isSandboxed()) {
                                    $usf = new UnSandboxForm($this->out, $this->profile, $r2args);
                                    $usf->show();
                                } else {
                                    $sf = new SandboxForm($this->out, $this->profile, $r2args);
                                    $sf->show();
                                }
                                $this->out->elementEnd('li');
                            }

                            if ($cur->hasRight(Right::SILENCEUSER)) {
                                $this->out->elementStart('li', 'entity_silence');
                                if ($this->profile->isSilenced()) {
                                    $usf = new UnSilenceForm($this->out, $this->profile, $r2args);
                                    $usf->show();
                                } else {
                                    $sf = new SilenceForm($this->out, $this->profile, $r2args);
                                    $sf->show();
                                }
                                $this->out->elementEnd('li');
                            }

                            if ($isLocal && $cur->hasRight(Right::DELETEUSER)) {
                                $this->out->elementStart('li', 'entity_delete');
                                $df = new DeleteUserForm($this->out, $this->profile, $r2args);
                                $df->show();
                                $this->out->elementEnd('li');
                            }
                            $this->out->elementEnd('ul');
                            $this->out->elementEnd('li');
                        }

                        if ($isLocal && $cur->hasRight(Right::GRANTROLE)) {
                            $this->out->elementStart('li', 'entity_role');
                            // TRANS: Label text on user profile to select a user role.
                            $this->out->element('p', null, _('User role'));
                            $this->out->elementStart('ul');
                            // TRANS: Role that can be set for a user profile.
                            $this->roleButton('administrator', _m('role', 'Administrator'));
                            // TRANS: Role that can be set for a user profile.
                            $this->roleButton('moderator', _m('role', 'Moderator'));
                            $this->out->elementEnd('ul');
                            $this->out->elementEnd('li');
                        }
                    }
                }

                Event::handle('EndProfilePageActionsElements', array($this->out, $this->profile));
            }

            $this->out->elementEnd('ul');
            $this->out->elementEnd('div');

            Event::handle('EndProfilePageActionsSection', array($this->out, $this->profile));
        }
    }

    function roleButton($role, $label)
    {
        list($action, $r2args) = $this->out->returnToArgs();
        $r2args['action'] = $action;

        $this->out->elementStart('li', "entity_role_$role");
        if ($this->profile->hasRole($role)) {
            $rf = new RevokeRoleForm($role, $label, $this->out, $this->profile, $r2args);
            $rf->show();
        } else {
            $rf = new GrantRoleForm($role, $label, $this->out, $this->profile, $r2args);
            $rf->show();
        }
        $this->out->elementEnd('li');
    }

    function showRemoteSubscribeLink()
    {
        $url = common_local_url('remotesubscribe',
                                array('nickname' => $this->profile->nickname));
        $this->out->element('a', array('href' => $url,
                                  'class' => 'entity_remote_subscribe'),
                       // TRANS: Link text for link that will subscribe to a remote profile.
                       _('Subscribe'));
    }
}

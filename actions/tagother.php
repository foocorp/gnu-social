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

require_once(INSTALLDIR.'/lib/settingsaction.php');

// @todo FIXME: documentation missing.
class TagotherAction extends Action
{
    var $profile = null;
    var $error = null;

    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'), 403);
            return false;
        }

        $id = $this->trimmed('id');
        if (!$id) {
            $this->clientError(_('No ID argument.'));
            return false;
        }

        $this->profile = Profile::staticGet('id', $id);

        if (!$this->profile) {
            $this->clientError(_('No profile with that ID.'));
            return false;
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->saveTags();
        } else {
            $this->showForm($profile);
        }
    }

    function title()
    {
        return sprintf(_('Tag %s'), $this->profile->nickname);
    }

    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    function showContent()
    {
        $this->elementStart('div', 'entity_profile vcard author');
        $this->element('h2', null, _('User profile'));

        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        $this->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE),
                                    'class' => 'photo avatar entity_depiction',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' =>
                                    ($this->profile->fullname) ? $this->profile->fullname :
                                    $this->profile->nickname));

        $this->element('a', array('href' => $this->profile->profileurl,
                                  'class' => 'entity_nickname nickname'),
                       $this->profile->nickname);

        if ($this->profile->fullname) {
            $this->element('div', 'fn entity_fn', $this->profile->fullname);
        }

        if ($this->profile->location) {
            $this->element('div', 'label entity_location', $this->profile->location);
        }

        if ($this->profile->homepage) {
            $this->element('a', array('href' => $this->profile->homepage,
                                      'rel' => 'me',
                                      'class' => 'url entity_url'),
                           $this->profile->homepage);
        }

        if ($this->profile->bio) {
            $this->element('div', 'note entity_note', $this->profile->bio);
        }

        $this->elementEnd('div');

        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_tag_user',
                                           'class' => 'form_settings',
                                           'name' => 'tagother',
                                           'action' => common_local_url('tagother', array('id' => $this->profile->id))));

        $this->elementStart('fieldset');
        $this->element('legend', null, _('Tag user'));
        $this->hidden('token', common_session_token());
        $this->hidden('id', $this->profile->id);

        $user = common_current_user();

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('tags', _('Tags'),
                     ($this->arg('tags')) ? $this->arg('tags') : implode(' ', Profile_tag::getTags($user->id, $this->profile->id)),
                     _('Tags for this user (letters, numbers, -, ., and _), separated by commas or spaces.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('save', _m('BUTTON','Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function saveTags()
    {
        $id = $this->trimmed('id');
        $tagstring = $this->trimmed('tags');
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if (is_string($tagstring) && strlen($tagstring) > 0) {

            $tags = array_map('common_canonical_tag',
                              preg_split('/[\s,]+/', $tagstring));

            foreach ($tags as $tag) {
                if (!common_valid_profile_tag($tag)) {
                    // TRANS: Form validation error when entering an invalid tag.
                    // TRANS: %s is the invalid tag.
                    $this->showForm(sprintf(_('Invalid tag: "%s".'), $tag));
                    return;
                }
            }
        } else {
            $tags = array();
        }

        $user = common_current_user();

        if (!Subscription::pkeyGet(array('subscriber' => $user->id,
                                         'subscribed' => $this->profile->id)) &&
            !Subscription::pkeyGet(array('subscriber' => $this->profile->id,
                                         'subscribed' => $user->id)))
        {
            $this->clientError(_('You can only tag people you are subscribed to or who are subscribed to you.'));
            return;
        }

        $result = Profile_tag::setTags($user->id, $this->profile->id, $tags);

        if (!$result) {
            $this->clientError(_('Could not save tags.'));
            return;
        }

        $action = $user->isSubscribed($this->profile) ? 'subscriptions' : 'subscribers';

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Tags'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->elementStart('p', 'subtags');
            foreach ($tags as $tag) {
                $this->element('a', array('href' => common_local_url($action,
                                                                     array('nickname' => $user->nickname,
                                                                           'tag' => $tag))),
                               $tag);
            }
            $this->elementEnd('p');
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url($action, array('nickname' =>
                                                            $user->nickname)),
                            303);
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $this->elementStart('div', 'instructions');
            $this->element('p', null,
                           _('Use this form to add tags to your subscribers or subscriptions.'));
            $this->elementEnd('div');
        }
    }
}

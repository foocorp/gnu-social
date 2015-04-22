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

if (!defined('GNUSOCIAL')) { exit(1); }

class TagprofileAction extends FormAction
{
    var $error = null;

    protected $target = null;
    protected $form = 'TagProfile';

    protected function doPreparation()
    {
        $id = $this->trimmed('id');
        $uri = $this->trimmed('uri');
        if (!empty($id))  {
            $this->target = Profile::getKV('id', $id);

            if (!$this->target instanceof Profile) {
                // TRANS: Client error displayed when referring to non-existing profile ID.
                $this->clientError(_('No profile with that ID.'));
            }
        } elseif (!empty($uri)) {
            $this->target = Profile::fromUri($uri);
        } else {
            // TRANS: Client error displayed when trying to tag a user but no ID or profile is provided.
            $this->clientError(_('No profile identifier provided.'));
        }

        if (!$this->scoped->canTag($this->target)) {
            // TRANS: Client error displayed when trying to tag a user that cannot be tagged.
            $this->clientError(_('You cannot tag this user.'));
        }

        $this->formOpts = $this->target;

        return true;
    }

    function title()
    {
        if (!$this->target instanceof Profile) {
            // TRANS: Title for list form when not on a profile page.
            return _('List a profile');
        }
        // TRANS: Title for list form when on a profile page.
        // TRANS: %s is a profile nickname.
        return sprintf(_m('ADDTOLIST','List %s'), $this->target->getNickname());
    }

    function showContent()
    {
        $this->elementStart('div', 'entity_profile h-card');
        // TRANS: Header in list form.
        $this->element('h2', null, _('User profile'));

        $avatarUrl = $this->target->avatarUrl(AVATAR_PROFILE_SIZE);
        $this->element('img', array('src' => $avatarUrl,
                                    'class' => 'u-photo avatar entity_depiction',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $this->target->getBestName()));

        $this->element('a', array('href' => $this->target->getUrl(),
                                  'class' => 'entity_nickname p-nickname'),
                       $this->target->getNickname());
        if ($this->target->fullname) {
            $this->element('div', 'p-name entity_fn', $this->target->fullname);
        }

        if ($this->target->location) {
            $this->element('div', 'p-locality label entity_location', $this->target->location);
        }

        if ($this->target->homepage) {
            $this->element('a', array('href' => $this->target->homepage,
                                      'rel' => 'me',
                                      'class' => 'u-url entity_url'),
                           $this->target->homepage);
        }

        if ($this->target->bio) {
            $this->element('div', 'p-note entity_note', $this->target->bio);
        }

        $this->elementEnd('div');

        if (Event::handle('StartShowTagProfileForm', array($this, $this->target))) {
            parent::showContent();
            Event::handle('EndShowTagProfileForm', array($this, $this->target));
        }
    }

    protected function doPost()
    {
        $tagstring = $this->trimmed('tags');
        $token = $this->trimmed('token');

        if (Event::handle('StartSavePeopletags', array($this, $tagstring))) {
            $tags = array();
            $tag_priv = array();

            if (is_string($tagstring) && strlen($tagstring) > 0) {

                $tags = preg_split('/[\s,]+/', $tagstring);

                foreach ($tags as &$tag) {
                    $private = @$tag[0] === '.';

                    $tag = common_canonical_tag($tag);
                    if (!common_valid_profile_tag($tag)) {
                        // TRANS: Form validation error displayed if a given tag is invalid.
                        // TRANS: %s is the invalid tag.
                        throw new ClientException(sprintf(_('Invalid tag: "%s".'), $tag));
                    }

                    $tag_priv[$tag] = $private;
                }
            }

            $result = Profile_tag::setTags($this->scoped->getID(), $this->target->getID(), $tags, $tag_priv);
            if (!$result) {
                throw new ServerException('The tags could not be saved.');
            }

            if ($this->boolean('ajax')) {
                $this->startHTML('text/xml;charset=utf-8');
                $this->elementStart('head');
                $this->element('title', null, _m('TITLE','Tags'));
                $this->elementEnd('head');
                $this->elementStart('body');

                if ($this->scoped->id == $this->target->id) {
                    $widget = new SelftagsWidget($this, $this->scoped, $this->target);
                    $widget->show();
                } else {
                    $widget = new PeopletagsWidget($this, $this->scoped, $this->target);
                    $widget->show();
                }

                $this->elementEnd('body');
                $this->endHTML();
            } else {
                // TRANS: Success message if lists are saved.
                $this->msg = _('Lists saved.');
                $this->showForm();
            }

            Event::handle('EndSavePeopletags', array($this, $tagstring));
        }
    }
}

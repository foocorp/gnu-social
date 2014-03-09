<?php
/**
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/profilelist.php';

// 10x8

define('AVATARS_PER_PAGE', 80);

// @todo FIXME: Class documentation missing.
class GalleryAction extends ProfileAction
{
    var $profile = null;
    var $page = null;
    var $tag = null;

    function prepare($args)
    {
        parent::prepare($args);

        // FIXME very similar code below

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url($this->trimmed('action'), $args), 301);
        }

        $this->user = User::getKV('nickname', $nickname);

        if (!$this->user) {
            // TRANS: Client error displayed when trying to perform a gallery action with an unknown user.
            $this->clientError(_('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $this->tag = $this->trimmed('tag');
        $this->q   = $this->trimmed('q');

        return true;
    }

    function isReadOnly($args)
    {
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

		// Post from the tag dropdown; redirect to a GET

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		    common_redirect($this->selfUrl(), 303);
		}

        $this->showPage();
    }

    function showContent()
    {
        $this->showTagsDropdown();
    }

    function showTagsDropdown()
    {
        $tag = $this->trimmed('tag');

        $tags = $this->getAllTags();

        $content = array();

        foreach ($tags as $t) {
            $content[$t] = $t;
        }
        if ($tags) {
            $this->elementStart('dl', array('id' => 'filter_tags'));
            $this->element('dt', null, _('Tags'));
            $this->elementStart('dd');
            $this->elementStart('ul');
            $this->elementStart('li', array('id' => 'filter_tags_all',
                                             'class' => 'child_1'));
            $this->element('a',
                           array('href' =>
                                 common_local_url($this->trimmed('action'),
                                                  array('nickname' =>
                                                        $this->user->nickname))),
                           // TRANS: List element on gallery action page to show all tags.
                           _m('TAGS','All'));
            $this->elementEnd('li');
            $this->elementStart('li', array('id'=>'filter_tags_item'));
            $this->elementStart('form', array('name' => 'bytag',
                                               'id' => 'form_filter_bytag',
                                              'action' => common_path('?action=' . $this->trimmed('action')),
                                               'method' => 'post'));
            $this->elementStart('fieldset');
            // TRANS: Fieldset legend on gallery action page.
            $this->element('legend', null, _('Select tag to filter'));
            // TRANS: Dropdown field label on gallery action page for a list containing tags.
            $this->dropdown('tag', _('Tag'), $content,
                            // TRANS: Dropdown field title on gallery action page for a list containing tags.
                            _('Choose a tag to narrow list.'), false, $tag);
            $this->hidden('nickname', $this->user->nickname);
            // TRANS: Submit button text on gallery action page.
            $this->submit('submit', _m('BUTTON','Go'));
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
    }

    // Get list of tags we tagged other users with
    function getTags($lst, $usr)
    {
        $profile_tag = new Notice_tag();
        $profile_tag->query('SELECT DISTINCT(tag) ' .
                            'FROM profile_tag, subscription ' .
                            'WHERE tagger = ' . $this->profile->id . ' ' .
                            'AND ' . $usr . ' = ' . $this->profile->id . ' ' .
                            'AND ' . $lst . ' = tagged ' .
                            'AND tagger != tagged');
        $tags = array();
        while ($profile_tag->fetch()) {
            $tags[] = $profile_tag->tag;
        }
        $profile_tag->free();
        return $tags;
    }

    function getAllTags()
    {
        return array();
    }

    function showProfileBlock()
    {
        $block = new AccountProfileBlock($this, $this->profile);
        $block->show();
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a sortable list of profiles
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
 * @category  Public
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget to show a sortable list of subscriptions
 *
 * @category Public
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SortableGroupList extends SortableSubscriptionList
{
    function startList()
    {
        $this->out->elementStart('table', array('class' => 'profile_list xoxo'));
        $this->out->elementStart('thead');
        $this->out->elementStart('tr');

        $tableHeaders = array(
            // TRANS: Column header in table for user nickname.
            'nickname'    => _m('Nickname'),
            // TRANS: Column header in table for timestamp when user was created.
            'created'     => _m('Created')
        );

        foreach ($tableHeaders as $id => $label) {
            $attrs   = array('id' => $id);
            $current = (!empty($this->action->sort) && $this->action->sort == $id);

            if ($current || empty($this->action->sort) && $id == 'nickname') {
                $attrs['class'] = 'current';
            }

            if ($current && $this->action->reverse) {
                $attrs['class'] .= ' reverse';
                $attrs['class'] = trim($attrs['class']);
            }

            $this->out->elementStart('th', $attrs);

            $linkAttrs = array();
            $params    = array('sort' => $id);

            if (!empty($this->action->q)) {
                $params['q'] = $this->action->q;
            }

            if ($current && !$this->action->reverse) {
                $params['reverse'] = 'true';
            }

            $args = array();

            $filter = $this->action->arg('filter');

            if (!empty($filter)) {
                $args['filter'] = $filter;
            }

            $linkAttrs['href'] = common_local_url(
                $this->action->arg('action'), $args, $params
            );

            $this->out->element('a', $linkAttrs, $label);
            $this->out->elementEnd('th');
        }

        // TRANS: Column header in table for members of a group.
        $this->out->element('th', array('id' => 'Members'), _m('Members'));
        $this->out->element('th', array('id' => 'controls'), null);

        $this->out->elementEnd('tr');
        $this->out->elementEnd('thead');

        $this->out->elementStart('tbody');
    }

    function newListItem(Profile $profile)
    {
        return new SortableGroupListItem($profile, $this->owner, $this->action);
    }
}

class SortableGroupListItem extends SortableSubscriptionListItem
{
    function showHomepage()
    {
        if ($this->profile->getHomepage()) {
            $this->out->text(' ');
            $aAttrs = $this->homepageAttributes();
            $this->out->elementStart('a', $aAttrs);
            $this->out->text($this->profile->getHomepage());
            $this->out->elementEnd('a');
        }
    }

    function showDescription()
    {
        if ($this->profile->getDescription()) {
            $this->out->elementStart('p', 'note');
            $this->out->text($this->profile->getDescription());
            $this->out->elementEnd('p');
        }

    }

    // TODO: Make sure we can do ->getAvatar() for group profiles too!
    function showAvatar(Profile $profile, $size=null)
    {
        $logo = $profile->getGroup()->stream_logo ?: User_group::defaultLogo($size ?: $this->avatarSize());

        $this->out->element('img', array('src'    => $logo,
                                         'class'  => 'avatar u-photo',
                                         'width'  => AVATAR_STREAM_SIZE,
                                         'height' => AVATAR_STREAM_SIZE,
                                         'alt'    => $profile->getBestName()));
    }

    function show()
    {
        if (Event::handle('StartProfileListItem', array($this))) {
            $this->startItem();
            if (Event::handle('StartProfileListItemProfile', array($this))) {
                $this->showProfile();
                Event::handle('EndProfileListItemProfile', array($this));
            }

            // XXX Add events?
            $this->showCreatedDate();
            $this->showMemberCount();

            if (Event::handle('StartProfileListItemActions', array($this))) {
                $this->showActions();
                Event::handle('EndProfileListItemActions', array($this));
            }
            $this->endItem();

            Event::handle('EndProfileListItem', array($this));
        }
    }

    function showProfile()
    {
        $this->startProfile();

        $this->showAvatar($this->profile);
        $this->out->element('a', array('href'  => $this->profile->getUrl(),
                                            'class' => 'p-org p-nickname',
                                            'rel'   => 'contact group'),
                                 $this->profile->getNickname());

        $this->showFullName();
        $this->showLocation();
        $this->showHomepage();
        $this->showDescription(); // groups have this instead of bios
        // Relevant portion!
        $this->showTags();
        $this->endProfile();
    }

    function endActions()
    {
        // delete button
        $cur = common_current_user();
        list($action, $r2args) = $this->out->returnToArgs();
        $r2args['action'] = $action;
        if ($cur instanceof User && $cur->hasRight(Right::DELETEGROUP)) {
            $this->out->elementStart('li', 'entity_delete');
            $df = new DeleteGroupForm($this->out, $this->profile->getGroup(), $r2args);
            $df->show();
            $this->out->elementEnd('li');
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('td');
    }

    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showJoinButton();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showJoinButton()
    {
        $user = $this->owner;
        if ($user) {

            $this->out->elementStart('li', 'entity_subscribe');
            // XXX: special-case for user looking at own
            // subscriptions page
            if ($user->isMember($this->profile->getGroup())) {
                $lf = new LeaveForm($this->out, $this->profile->getGroup());
                $lf->show();
            } else if (!Group_block::isBlocked($this->profile->getGroup(), $user->getProfile())) {
                $jf = new JoinForm($this->out, $this->profile->getGroup());
                $jf->show();
            }

            $this->out->elementEnd('li');
        }
    }

    function showMemberCount()
    {
        $this->out->elementStart('td', 'entry_member_count');
        $this->out->text($this->profile->getGroup()->getMemberCount());
        $this->out->elementEnd('td');
    }

    function showCreatedDate()
    {
        $this->out->elementStart('td', 'entry_created');
        // @todo FIXME: Should we provide i18n for timestamps in core?
        $this->out->text(date('j M Y', strtotime($this->profile->created)));
        $this->out->elementEnd('td');
    }

    function showAdmins()
    {
        $this->out->elementStart('td', 'entry_admins');
        // @todo
        $this->out->text('gargargar');
        $this->out->elementEnd('td');
    }

    /**
     * Only show the tags if we're logged in
     */
    function showTags()
    {
         if (common_logged_in()) {
            parent::showTags();
        }

    }
}

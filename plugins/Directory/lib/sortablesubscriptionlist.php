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
class SortableSubscriptionList extends SubscriptionList
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

        // TRANS: Column header for number of subscriptions.
        $this->out->element('th', array('id' => 'subscriptions'), _m('Subscriptions'));
        // TRANS: Column header for number of notices.
        $this->out->element('th', array('id' => 'notices'), _m('Notices'));
        $this->out->element('th', array('id' => 'controls'), null);

        $this->out->elementEnd('tr');
        $this->out->elementEnd('thead');

        $this->out->elementStart('tbody');
    }

    function endList()
    {
        $this->out->elementEnd('tbody');
        $this->out->elementEnd('table');
    }

    function newListItem($profile)
    {
        return new SortableSubscriptionListItem($profile, $this->owner, $this->action);
    }
}

class SortableSubscriptionListItem extends SubscriptionListItem
{
    function startItem()
    {
        $attr = array(
            'class' => 'profile',
            'id'    => 'profile-' . $this->profile->id
        );

        $this->out->elementStart('tr', $attr);
    }

    function endItem()
    {
        $this->out->elementEnd('tr');
    }

    function startProfile()
    {
        $this->out->elementStart('td', 'entity_profile h-card');
    }

    function endProfile()
    {
        $this->out->elementEnd('td');
    }

    function startActions()
    {
        $this->out->elementStart('td', 'entity_actions');
        $this->out->elementStart('ul');
    }

    function endActions()
    {
		
		// delete button
		$cur = common_current_user();
        list($action, $r2args) = $this->out->returnToArgs();
        $r2args['action'] = $action;        
		if ($cur instanceof User && $cur->hasRight(Right::DELETEUSER)) {
			$this->out->elementStart('li', 'entity_delete');
			$df = new DeleteUserForm($this->out, $this->profile, $r2args);
			$df->show();		
			$this->out->elementEnd('li');
		}
			
        $this->out->elementEnd('ul');
        $this->out->elementEnd('td');
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
            $this->showSubscriberCount();
            $this->showNoticeCount();

            if (Event::handle('StartProfileListItemActions', array($this))) {
                $this->showActions();
                Event::handle('EndProfileListItemActions', array($this));
            }
            $this->endItem();
            Event::handle('EndProfileListItem', array($this));
        }
    }

    function showSubscriberCount()
    {
        $this->out->elementStart('td', 'entry_subscriber_count');
        $this->out->raw($this->profile->subscriberCount());
        $this->out->elementEnd('td');
    }

    function showCreatedDate()
    {
        $this->out->elementStart('td', 'entry_created');
        $this->out->raw(date('j M Y', strtotime($this->profile->created)));
        $this->out->elementEnd('td');
    }

    function showNoticeCount()
    {
        $this->out->elementStart('td', 'entry_notice_count');
        $this->out->raw($this->profile->noticeCount());
        $this->out->elementEnd('td');
    }

    /**
     * Overrided to truncate the bio if it's real long, because it
     * looks better that way in the SortableSubscriptionList's table
     */
    function showBio()
    {
        if (!empty($this->profile->bio)) {
            $cutoff = 140; // XXX Should this be configurable?
            $bio    = htmlspecialchars($this->profile->bio);

            if (mb_strlen($bio) > $cutoff) {
                $bio = mb_substr($bio, 0, $cutoff - 1)
                    .'<a href="' . $this->profile->profileurl .'">…</a>';
            }

            $this->out->elementStart('p', 'note');
            $this->out->raw($bio);
            $this->out->elementEnd('p');
        }
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

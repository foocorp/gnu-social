<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List a user's subscribers
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
 * @category  Social
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * List a user's subscribers
 *
 * @category Social
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SubscribersAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Header for list of subscribers for a user (first page).
            // TRANS: %s is the user's nickname.
            return sprintf(_('%s subscribers'), $this->target->getNickname());
        } else {
            // TRANS: Header for list of subscribers for a user (not first page).
            // TRANS: %1$s is the user's nickname, $2$d is the page number.
            return sprintf(_('%1$s subscribers, page %2$d'),
                           $this->target->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        if ($this->scoped instanceof Profile && $this->scoped->id === $this->target->id) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscribers
                           // TRANS: of the logged in user's own profile.
                           _('These are the people who listen to '.
                             'your notices.'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscribers of a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_('These are the people who '.
                                     'listen to %s\'s notices.'),
                                   $this->target->getNickname()));
        }
    }

    function showContent()
    {
        parent::showContent();

        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        $cnt = 0;

        if ($this->tag) {
            $subscribers = $this->target->getTaggedSubscribers($this->tag, $offset, $limit);
        } else {
            $subscribers = $this->target->getSubscribers($offset, $limit);
        }

        if ($subscribers) {
            $subscribers_list = new SubscribersList($subscribers, $this->target, $this);
            $cnt = $subscribers_list->show();
            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }
        }

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'subscribers',
                          array('nickname' => $this->target->getNickname()));
    }

    function showEmptyListMessage()
    {
        if ($this->scoped instanceof Profile && $this->target->id === $this->scoped->id) {
            // TRANS: Subscriber list text when the logged in user has no subscribers.
            $message = _('You have no subscribers. Try subscribing to people you know and they might return the favor.');
        } elseif ($this->scoped instanceof Profile) {
            // TRANS: Subscriber list text when looking at the subscribers for a of a user other
            // TRANS: than the logged in user that has no subscribers. %s is the user nickname.
            $message = sprintf(_('%s has no subscribers. Want to be the first?'), $this->target->getNickname());
        } else {
            // TRANS: Subscriber list text when looking at the subscribers for a of a user that has none
            // TRANS: as an anonymous user. %s is the user nickname.
            // TRANS: This message contains a Markdown URL. The link description is between
            // TRANS: square brackets, and the link between parentheses. Do not separate "]("
            // TRANS: and do not change the URL part.
            $message = sprintf(_('%s has no subscribers. Why not [register an account](%%%%action.register%%%%) and be the first?'), $this->target->getNickname());
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showSections()
    {
        parent::showSections();
    }
}

class SubscribersList extends SubscriptionList
{
    function newListItem($profile)
    {
        return new SubscribersListItem($profile, $this->owner, $this->action);
    }
}

class SubscribersListItem extends SubscriptionListItem
{
    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            // Relevant code!
            $this->showBlockForm();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showBlockForm()
    {
        $user = common_current_user();

        if (!empty($user) && $this->owner->id == $user->id) {
            $returnto = array('action' => 'subscribers',
                              'nickname' => $this->owner->getNickname());
            $page = $this->out->arg('page');
            if ($page) {
                $returnto['param-page'] = $page;
            }
            $bf = new BlockForm($this->out, $this->profile, $returnto);
            $bf->show();
        }
    }

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'subscribers')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'subscribers')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of a user's subscriptions
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * A list of the user's subscriptions
 *
 * @category Social
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SearchSubsAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Header for subscriptions overview for a user (first page).
            // TRANS: %s is a user nickname.
            return sprintf(_m('%s\'s search subscriptions'), $this->getTarget()->getNickname());
        } else {
            // TRANS: Header for subscriptions overview for a user (not first page).
            // TRANS: %1$s is a user nickname, %2$d is the page number.
            return sprintf(_m('%1$s\'s search subscriptions, page %2$d'),
                           $this->getTarget()->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->getTarget())) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all search subscriptions
                           // TRANS: of the logged in user's own profile.
                           _m('You have subscribed to receive all notices on this site matching the following searches:'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscriptions of a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_m('%s has subscribed to receive all notices on this site matching the following searches:'),
                                   $this->getTarget()->getNickname()));
        }
    }

    function showContent()
    {
        if (Event::handle('StartShowTagSubscriptionsContent', array($this))) {
            parent::showContent();

            $offset = ($this->page-1) * PROFILES_PER_PAGE;
            $limit =  PROFILES_PER_PAGE + 1;

            $cnt = 0;

            $searchsub = new SearchSub();
            $searchsub->profile_id = $this->getTarget()->getID();
            $searchsub->limit($limit, $offset);
            $searchsub->find();

            if ($searchsub->N) {
                $list = new SearchSubscriptionsList($searchsub, $this->getTarget(), $this);
                $cnt = $list->show();
                if (0 == $cnt) {
                    $this->showEmptyListMessage();
                }
            } else {
                $this->showEmptyListMessage();
            }

            $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                              $this->page, 'searchsubs',
                              array('nickname' => $this->getTarget()->getNickname()));


            Event::handle('EndShowTagSubscriptionsContent', array($this));
        }
    }

    function showEmptyListMessage()
    {
        if (common_logged_in()) {
            if ($this->scoped->sameAs($this->getTarget())) {
                // TRANS: Search subscription list text when the logged in user has no search subscriptions.
                $message = _m('You are not subscribed to any text searches right now. You can push the "Subscribe" button ' .
                             'on any notice text search to automatically receive any public messages on this site that match that ' .
                             'search, even if you are not subscribed to the poster.');
            } else {
                // TRANS: Search subscription list text when looking at the subscriptions for a of a user other
                // TRANS: than the logged in user that has no search subscriptions. %s is the user nickname.
                $message = sprintf(_m('%s is not subscribed to any searches.'), $this->getTarget()->getNickname());
            }
        }
        else {
            // TRANS: Subscription list text when looking at the subscriptions for a of a user that has none
            // TRANS: as an anonymous user. %s is the user nickname.
            $message = sprintf(_m('%s is not subscribed to any searches.'), $this->getTarget()->getNickname());
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}

// XXX SubscriptionsList and SubscriptionList are dangerously close

class SearchSubscriptionsList extends SubscriptionList
{
    function newListItem($searchsub)
    {
        return new SearchSubscriptionsListItem($searchsub, $this->owner, $this->action);
    }
}

class SearchSubscriptionsListItem extends SubscriptionListItem
{
    function startItem()
    {
        $this->out->elementStart('li', array('class' => 'searchsub'));
    }

    function showProfile()
    {
        $searchsub = $this->profile;
        $search = $searchsub->search;

        // Relevant portion!
        $cur = common_current_user();
        if (!empty($cur) && $cur->id == $this->owner->id) {
            $this->showOwnerControls();
        }

        $url = common_local_url('noticesearch', array('q' => $search));
        // TRANS: Search subscription list item. %1$s is a URL to a notice search,
        // TRANS: %2$s are the search criteria, %3$s is a datestring.
        $linkline = sprintf(_m('"<a href="%1$s">%2$s</a>" since %3$s'),
                            htmlspecialchars($url),
                            htmlspecialchars($search),
                            common_date_string($searchsub->created));

        $this->out->elementStart('div', 'searchsub-item');
        $this->out->raw($linkline);
        $this->out->element('div', array('style' => 'clear: both'));
        $this->out->elementEnd('div');
    }

    function showActions()
    {
    }

    function showOwnerControls()
    {
        $this->out->elementStart('div', 'entity_actions');

        $searchsub = $this->profile; // ?
        $form = new SearchUnsubForm($this->out, $searchsub->search);
        $form->show();

        $this->out->elementEnd('div');
        return;
    }
}

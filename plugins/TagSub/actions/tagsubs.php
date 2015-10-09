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
class TagSubsAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Header for subscriptions overview for a user (first page).
            // TRANS: %s is a user nickname.
            return sprintf(_m('%s\'s tag subscriptions'), $this->getTarget()->getNickname());
        } else {
            // TRANS: Header for subscriptions overview for a user (not first page).
            // TRANS: %1$s is a user nickname, %2$d is the page number.
            return sprintf(_m('%1$s\'s tag subscriptions, page %2$d'),
                           $this->getTarget()->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->getTarget())) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all tag subscriptions
                           // TRANS: of the logged in user's own profile.
                           _m('You have subscribed to receive all notices on this site containing the following tags:'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscriptions of a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_m('%s has subscribed to receive all notices on this site containing the following tags:'),
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

            $tagsub = new TagSub();
            $tagsub->profile_id = $this->getTarget()->getID();
            $tagsub->limit($limit, $offset);
            $tagsub->find();

            if ($tagsub->N) {
                $list = new TagSubscriptionsList($tagsub, $this->getTarget(), $this);
                $cnt = $list->show();
                if (0 == $cnt) {
                    $this->showEmptyListMessage();
                }
            } else {
                $this->showEmptyListMessage();
            }

            $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                              $this->page, 'tagsubs',
                              array('nickname' => $this->getTarget()->getNickname()));


            Event::handle('EndShowTagSubscriptionsContent', array($this));
        }
    }

    function showEmptyListMessage()
    {
        if (common_logged_in()) {
            if ($this->scoped->sameAs($this->getTarget())) {
                // TRANS: Tag subscription list text when the logged in user has no tag subscriptions.
                $message = _m('You are not listening to any hash tags right now. You can push the "Subscribe" button ' .
                              'on any hashtag page to automatically receive any public messages on this site that use that ' .
                              'tag, even if you are not subscribed to the poster.');
            } else {
                // TRANS: Tag subscription list text when looking at the subscriptions for a of a user other
                // TRANS: than the logged in user that has no tag subscriptions. %s is the user nickname.
                $message = sprintf(_m('%s is not following any tags.'), $this->getTarget()->getNickname());
            }
        }
        else {
            // TRANS: Subscription list text when looking at the subscriptions for a of a user that has none
            // TRANS: as an anonymous user. %s is the user nickname.
            $message = sprintf(_m('%s is not following any tags.'), $this->getTarget()->getNickname());
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}

// XXX SubscriptionsList and SubscriptionList are dangerously close

class TagSubscriptionsList extends SubscriptionList
{
    function newListItem($tagsub)
    {
        return new TagSubscriptionsListItem($tagsub, $this->owner, $this->action);
    }
}

class TagSubscriptionsListItem extends SubscriptionListItem
{
    function startItem()
    {
        $this->out->elementStart('li', array('class' => 'tagsub'));
    }

    function showProfile()
    {
        $tagsub = $this->profile;
        $tag = $tagsub->tag;

        // Relevant portion!
        $cur = common_current_user();
        if (!empty($cur) && $cur->id == $this->owner->id) {
            $this->showOwnerControls();
        }

        $url = common_local_url('tag', array('tag' => $tag));
        // TRANS: %1$s is a URL to a tag, %2$s is a tag,
        // TRANS: %3$s a date string.
        $linkline = sprintf(_m('#<a href="%1$s">%2$s</a> since %3$s'),
                            htmlspecialchars($url),
                            htmlspecialchars($tag),
                            common_date_string($tagsub->created));

        $this->out->elementStart('div', 'tagsub-item');
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

        $tagsub = $this->profile; // ?
        $form = new TagUnsubForm($this->out, $tagsub->tag);
        $form->show();

        $this->out->elementEnd('div');
        return;
    }
}

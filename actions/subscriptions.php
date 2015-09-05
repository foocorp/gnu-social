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
class SubscriptionsAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Header for subscriptions overview for a user (first page).
            // TRANS: %s is a user nickname.
            return sprintf(_('%s subscriptions'), $this->target->getNickname());
        } else {
            // TRANS: Header for subscriptions overview for a user (not first page).
            // TRANS: %1$s is a user nickname, %2$d is the page number.
            return sprintf(_('%1$s subscriptions, page %2$d'),
                           $this->target->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->getTarget())) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscriptions
                           // TRANS: of the logged in user's own profile.
                           _('These are the people whose notices '.
                             'you listen to.'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscriptions of a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_('These are the people whose '.
                                     'notices %s listens to.'),
                                   $this->target->getNickname()));
        }
    }

    function getAllTags()
    {
        return $this->getTags('subscribed', 'subscriber');
    }

    function showContent()
    {
        if (Event::handle('StartShowSubscriptionsContent', array($this))) {
            parent::showContent();

            $offset = ($this->page-1) * PROFILES_PER_PAGE;
            $limit =  PROFILES_PER_PAGE + 1;

            $cnt = 0;

            if ($this->tag) {
                $subscriptions = $this->target->getTaggedSubscriptions($this->tag, $offset, $limit);
            } else {
                $subscriptions = $this->target->getSubscribed($offset, $limit);
            }

            if ($subscriptions) {
                $subscriptions_list = new SubscriptionsList($subscriptions, $this->target, $this);
                $cnt = $subscriptions_list->show();
                if (0 == $cnt) {
                    $this->showEmptyListMessage();
                }
            }

            $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                              $this->page, 'subscriptions',
                              array('nickname' => $this->target->getNickname()));


            Event::handle('EndShowSubscriptionsContent', array($this));
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('tag');
    }

    function showEmptyListMessage()
    {
        if ($this->scoped instanceof Profile && $this->target->id === $this->scoped->id) {
                // TRANS: Subscription list text when the logged in user has no subscriptions.
                // TRANS: This message contains Markdown URLs. The link description is between
                // TRANS: square brackets, and the link between parentheses. Do not separate "]("
                // TRANS: and do not change the URL part.
                $message = _('You\'re not listening to anyone\'s notices right now, try subscribing to people you know. '.
                             'Try [people search](%%action.peoplesearch%%), look for members in groups you\'re interested '.
                             'in and in our [featured users](%%action.featured%%).');
        } else {
            // TRANS: Subscription list text when looking at the subscriptions for a of a user that has none
            // TRANS: as an anonymous user. %s is the user nickname.
            $message = sprintf(_('%s is not listening to anyone.'), $this->target->getNickname());
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Link to feeds of subscriptions
     *
     * @return array of Feed objects
     */
    function getFeeds()
    {
        return array(new Feed(Feed::ATOM,
                              common_local_url('AtomPubSubscriptionFeed',
                                               array('subscriber' => $this->target->id)),
                              // TRANS: Atom feed title. %s is a profile nickname.
                              sprintf(_('Subscription feed for %s (Atom)'),
                                      $this->target->getNickname())));
    }
}

// XXX SubscriptionsList and SubscriptionList are dangerously close

class SubscriptionsList extends SubscriptionList
{
    function newListItem($profile)
    {
        return new SubscriptionsListItem($profile, $this->owner, $this->action);
    }
}

class SubscriptionsListItem extends SubscriptionListItem
{
    function showOwnerControls()
    {
        $sub = Subscription::pkeyGet(array('subscriber' => $this->owner->id,
                                           'subscribed' => $this->profile->id));
        if (!$sub) {
            return;
        }

        $transports = array();
        Event::handle('GetImTransports', array(&$transports));
        if (!$transports && !common_config('sms', 'enabled')) {
            return;
        }

        $this->out->elementStart('form', array('id' => 'subedit-' . $this->profile->id,
                                          'method' => 'post',
                                          'class' => 'form_subscription_edit',
                                          'action' => common_local_url('subedit')));
        $this->out->hidden('token', common_session_token());
        $this->out->hidden('profile', $this->profile->id);
        if ($transports) {
            $attrs = array('name' => 'jabber',
                           'type' => 'checkbox',
                           'class' => 'checkbox',
                           'id' => 'jabber-'.$this->profile->id);
            if ($sub->jabber) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);
            // TRANS: Checkbox label for enabling IM messages for a profile in a subscriptions list.
            $this->out->element('label', array('for' => 'jabber-'.$this->profile->id), _m('LABEL','IM'));
        } else {
            $this->out->hidden('jabber', $sub->jabber);
        }
        if (common_config('sms', 'enabled')) {
            $attrs = array('name' => 'sms',
                           'type' => 'checkbox',
                           'class' => 'checkbox',
                           'id' => 'sms-'.$this->profile->id);
            if ($sub->sms) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);
            // TRANS: Checkbox label for enabling SMS messages for a profile in a subscriptions list.
            $this->out->element('label', array('for' => 'sms-'.$this->profile->id), _('SMS'));
        } else {
            $this->out->hidden('sms', $sub->sms);
        }
        // TRANS: Save button for settings for a profile in a subscriptions list.
        $this->out->submit('save', _m('BUTTON','Save'));
        $this->out->elementEnd('form');
    }
}

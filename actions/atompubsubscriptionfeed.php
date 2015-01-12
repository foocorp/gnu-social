<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * AtomPub subscription feed
 *
 * PHP version 5
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
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

/**
 * Subscription feed class for AtomPub
 *
 * Generates a list of the user's subscriptions
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class AtompubsubscriptionfeedAction extends AtompubAction
{
    private $_profile       = null;
    private $_subscriptions = null;

    protected function atompubPrepare()
    {
        $subscriber = $this->trimmed('subscriber');

        $this->_profile = Profile::getKV('id', $subscriber);

        if (!$this->_profile instanceof Profile) {
            // TRANS: Client exception thrown when trying to display a subscription for a non-existing profile ID.
            // TRANS: %d is the non-existing profile ID number.
            throw new ClientException(sprintf(_('No such profile id: %d.'),
                                              $subscriber), 404);
        }

        $this->_subscriptions = Subscription::bySubscriber($this->_profile->id,
                                                           $this->offset,
                                                           $this->limit);

        return true;
    }

    protected function handleGet()
    {
        $this->showFeed();
    }

    protected function handlePost()
    {
        $this->addSubscription();
    }

    /**
     * Show the feed of subscriptions
     *
     * @return void
     */
    function showFeed()
    {
        header('Content-Type: application/atom+xml; charset=utf-8');

        $url = common_local_url('AtomPubSubscriptionFeed',
                                array('subscriber' => $this->_profile->id));

        $feed = new Atom10Feed(true);

        $feed->addNamespace('activity',
                            'http://activitystrea.ms/spec/1.0/');

        $feed->addNamespace('poco',
                            'http://portablecontacts.net/spec/1.0');

        $feed->addNamespace('media',
                            'http://purl.org/syndication/atommedia');

        $feed->addNamespace('georss',
                            'http://www.georss.org/georss');

        $feed->id = $url;

        $feed->setUpdated('now');

        $feed->addAuthor($this->_profile->getBestName(),
                         $this->_profile->getURI());

        // TRANS: Title for Atom subscription feed.
        // TRANS: %s is a user nickname.
        $feed->setTitle(sprintf(_("%s subscriptions"),
                                $this->_profile->getBestName()));

        // TRANS: Subtitle for Atom subscription feed.
        // TRANS: %1$s is a user nickname, %s$s is the StatusNet sitename.
        $feed->setSubtitle(sprintf(_("People %1\$s has subscribed to on %2\$s"),
                                   $this->_profile->getBestName(),
                                   common_config('site', 'name')));

        $feed->addLink(common_local_url('subscriptions',
                                        array('nickname' =>
                                              $this->_profile->nickname)));

        $feed->addLink($url,
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));

        // If there's more...

        if ($this->page > 1) {
            $feed->addLink($url,
                           array('rel' => 'first',
                                 'type' => 'application/atom+xml'));

            $feed->addLink(common_local_url('AtomPubSubscriptionFeed',
                                            array('subscriber' =>
                                                  $this->_profile->id),
                                            array('page' =>
                                                  $this->page - 1)),
                           array('rel' => 'prev',
                                 'type' => 'application/atom+xml'));
        }

        if ($this->_subscriptions->N > $this->count) {

            $feed->addLink(common_local_url('AtomPubSubscriptionFeed',
                                            array('subscriber' =>
                                                  $this->_profile->id),
                                            array('page' =>
                                                  $this->page + 1)),
                           array('rel' => 'next',
                                 'type' => 'application/atom+xml'));
        }

        $i = 0;

        // XXX: This is kind of inefficient

        while ($this->_subscriptions->fetch()) {

            // We get one more than needed; skip that one

            $i++;

            if ($i > $this->count) {
                break;
            }

            $act = $this->_subscriptions->asActivity();
            $feed->addEntryRaw($act->asString(false, false, false));
        }

        $this->raw($feed->getString());
    }

    /**
     * Add a new subscription
     *
     * Handling the POST method for AtomPub
     *
     * @return void
     */
    function addSubscription()
    {
        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_profile->id) {
            // TRANS: Client exception thrown when trying to subscribe another user.
            throw new ClientException(_("Cannot add someone else's".
                                        " subscription."), 403);
        }

        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            // TRANS: Client error displayed when not using an Atom entry.
            $this->clientError(_('Atom post must be an Atom entry.'));
        }

        $activity = new Activity($dom->documentElement);

        $sub = null;

        if (Event::handle('StartAtomPubNewActivity', array(&$activity))) {

            if ($activity->verb != ActivityVerb::FOLLOW) {
                // TRANS: Client error displayed when not using the follow verb.
                $this->clientError(_('Can only handle Follow activities.'));
            }

            $person = $activity->objects[0];

            if ($person->type != ActivityObject::PERSON) {
                // TRANS: Client exception thrown when subscribing to an object that is not a person.
                $this->clientError(_('Can only follow people.'));
            }

            // XXX: OStatus discovery (maybe)
            try {
                $profile = Profile::fromUri($person->id);
            } catch (UnknownUriException $e) {
                // TRANS: Client exception thrown when subscribing to a non-existing profile.
                // TRANS: %s is the unknown profile ID.
                $this->clientError(sprintf(_('Unknown profile %s.'), $person->id));
            }

            if (Subscription::exists($this->_profile, $profile)) {
                // 409 Conflict
                // TRANS: Client error displayed trying to subscribe to an already subscribed profile.
                // TRANS: %s is the profile the user already has a subscription on.
                $this->clientError(sprintf(_('Already subscribed to %s.'),
                                           $person->id),
                                   409);
            }

            if (Subscription::start($this->_profile, $profile)) {
                $sub = Subscription::pkeyGet(array('subscriber' => $this->_profile->id,
                                                   'subscribed' => $profile->id));
            }

            Event::handle('EndAtomPubNewActivity', array($activity, $sub));
        }

        if (!empty($sub)) {
            $act = $sub->asActivity();

            header('Content-Type: application/atom+xml; charset=utf-8');
            header('Content-Location: ' . $act->selfLink);

            $this->startXML();
            $this->raw($act->asString(true, true, true));
            $this->endXML();
        }
    }
}

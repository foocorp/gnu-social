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

/**
 * Table Definition for subscription
 */
class Subscription extends Managed_DataObject
{
    const CACHE_WINDOW = 201;
    const FORCE = true;

    public $__table = 'subscription';                    // table name
    public $subscriber;                      // int(4)  primary_key not_null
    public $subscribed;                      // int(4)  primary_key not_null
    public $jabber;                          // tinyint(1)   default_1
    public $sms;                             // tinyint(1)   default_1
    public $token;                           // varchar(255)
    public $secret;                          // varchar(255)
    public $uri;                             // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'profile listening'),
                'subscribed' => array('type' => 'int', 'not null' => true, 'description' => 'profile being listened to'),
                'jabber' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'deliver jabber messages'),
                'sms' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'deliver sms messages'),
                'token' => array('type' => 'varchar', 'length' => 255, 'description' => 'authorization token'),
                'secret' => array('type' => 'varchar', 'length' => 255, 'description' => 'token secret'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('subscriber', 'subscribed'),
            'unique keys' => array(
                'subscription_uri_key' => array('uri'),
            ),
            'indexes' => array(
                'subscription_subscriber_idx' => array('subscriber', 'created'),
                'subscription_subscribed_idx' => array('subscribed', 'created'),
                'subscription_token_idx' => array('token'),
            ),
        );
    }

    /**
     * Make a new subscription
     *
     * @param Profile $subscriber party to receive new notices
     * @param Profile $other      party sending notices; publisher
     * @param bool    $force      pass Subscription::FORCE to override local subscription approval
     *
     * @return mixed Subscription or Subscription_queue: new subscription info
     */

    static function start(Profile $subscriber, Profile $other, $force=false)
    {
        if (!$subscriber->hasRight(Right::SUBSCRIBE)) {
            // TRANS: Exception thrown when trying to subscribe while being banned from subscribing.
            throw new Exception(_('You have been banned from subscribing.'));
        }

        if (self::exists($subscriber, $other)) {
            // TRANS: Exception thrown when trying to subscribe while already subscribed.
            throw new AlreadyFulfilledException(_('Already subscribed!'));
        }

        if ($other->hasBlocked($subscriber)) {
            // TRANS: Exception thrown when trying to subscribe to a user who has blocked the subscribing user.
            throw new Exception(_('User has blocked you.'));
        }

        if (Event::handle('StartSubscribe', array($subscriber, $other))) {
            $otherUser = User::getKV('id', $other->id);
            if ($otherUser instanceof User && $otherUser->subscribe_policy == User::SUBSCRIBE_POLICY_MODERATE && !$force) {
                $sub = Subscription_queue::saveNew($subscriber, $other);
                $sub->notify();
            } else {
                $sub = self::saveNew($subscriber->id, $other->id);
                $sub->notify();

                self::blow('user:notices_with_friends:%d', $subscriber->id);

                self::blow('subscription:by-subscriber:'.$subscriber->id);
                self::blow('subscription:by-subscribed:'.$other->id);

                $subscriber->blowSubscriptionCount();
                $other->blowSubscriberCount();

                if ($otherUser instanceof User &&
                    $otherUser->autosubscribe &&
                    !self::exists($other, $subscriber) &&
                    !$subscriber->hasBlocked($other)) {

                    try {
                        self::start($other, $subscriber);
                    } catch (AlreadyFulfilledException $e) {
                        // This shouldn't happen due to !self::exists above
                        common_debug('Tried to autosubscribe a user to its new subscriber.');
                    } catch (Exception $e) {
                        common_log(LOG_ERR, "Exception during autosubscribe of {$other->nickname} to profile {$subscriber->id}: {$e->getMessage()}");
                    }
                }
            }

            Event::handle('EndSubscribe', array($subscriber, $other));
        }

        return $sub;
    }

    /**
     * Low-level subscription save.
     * Outside callers should use Subscription::start()
     */
    protected function saveNew($subscriber_id, $other_id)
    {
        $sub = new Subscription();

        $sub->subscriber = $subscriber_id;
        $sub->subscribed = $other_id;
        $sub->jabber     = 1;
        $sub->sms        = 1;
        $sub->created    = common_sql_now();
        $sub->uri        = self::newURI($sub->subscriber,
                                        $sub->subscribed,
                                        $sub->created);

        $result = $sub->insert();

        if ($result===false) {
            common_log_db_error($sub, 'INSERT', __FILE__);
            // TRANS: Exception thrown when a subscription could not be stored on the server.
            throw new Exception(_('Could not save subscription.'));
        }

        return $sub;
    }

    function notify()
    {
        // XXX: add other notifications (Jabber, SMS) here
        // XXX: queue this and handle it offline
        // XXX: Whatever happens, do it in Twitter-like API, too

        $this->notifyEmail();
    }

    function notifyEmail()
    {
        $subscribedUser = User::getKV('id', $this->subscribed);

        if ($subscribedUser instanceof User) {

            $subscriber = Profile::getKV('id', $this->subscriber);

            mail_subscribe_notify_profile($subscribedUser, $subscriber);
        }
    }

    /**
     * Cancel a subscription
     *
     */
    static function cancel(Profile $subscriber, Profile $other)
    {
        if (!self::exists($subscriber, $other)) {
            // TRANS: Exception thrown when trying to unsibscribe without a subscription.
            throw new AlreadyFulfilledException(_('Not subscribed!'));
        }

        // Don't allow deleting self subs

        if ($subscriber->id == $other->id) {
            // TRANS: Exception thrown when trying to unsubscribe a user from themselves.
            throw new Exception(_('Could not delete self-subscription.'));
        }

        if (Event::handle('StartUnsubscribe', array($subscriber, $other))) {

            $sub = Subscription::pkeyGet(array('subscriber' => $subscriber->id,
                                               'subscribed' => $other->id));

            // note we checked for existence above

            assert(!empty($sub));

            $result = $sub->delete();

            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                // TRANS: Exception thrown when a subscription could not be deleted on the server.
                throw new Exception(_('Could not delete subscription.'));
            }

            self::blow('user:notices_with_friends:%d', $subscriber->id);

            self::blow('subscription:by-subscriber:'.$subscriber->id);
            self::blow('subscription:by-subscribed:'.$other->id);

            $subscriber->blowSubscriptionCount();
            $other->blowSubscriberCount();

            Event::handle('EndUnsubscribe', array($subscriber, $other));
        }

        return;
    }

    static function exists(Profile $subscriber, Profile $other)
    {
        $sub = Subscription::pkeyGet(array('subscriber' => $subscriber->id,
                                           'subscribed' => $other->id));
        return ($sub instanceof Subscription);
    }

    function asActivity()
    {
        $subscriber = Profile::getKV('id', $this->subscriber);
        $subscribed = Profile::getKV('id', $this->subscribed);

        if (!$subscriber instanceof Profile) {
            throw new NoProfileException($this->subscriber);
        }

        if (!$subscribed instanceof Profile) {
            throw new NoProfileException($this->subscribed);
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FOLLOW;

        // XXX: rationalize this with the URL

        $act->id   = $this->getURI();

        $act->time    = strtotime($this->created);
        // TRANS: Activity title when subscribing to another person.
        $act->title = _m('TITLE','Follow');
        // TRANS: Notification given when one person starts following another.
        // TRANS: %1$s is the subscriber, %2$s is the subscribed.
        $act->content = sprintf(_('%1$s is now following %2$s.'),
                               $subscriber->getBestName(),
                               $subscribed->getBestName());

        $act->actor     = ActivityObject::fromProfile($subscriber);
        $act->objects[] = ActivityObject::fromProfile($subscribed);

        $url = common_local_url('AtomPubShowSubscription',
                                array('subscriber' => $subscriber->id,
                                      'subscribed' => $subscribed->id));

        $act->selfLink = $url;
        $act->editLink = $url;

        return $act;
    }

    /**
     * Stream of subscriptions with the same subscriber
     *
     * Useful for showing pages that list subscriptions in reverse
     * chronological order. Has offset & limit to make paging
     * easy.
     *
     * @param integer $profile_id   ID of the subscriber profile
     * @param integer $offset       Offset from latest
     * @param integer $limit        Maximum number to fetch
     *
     * @return Subscription stream of subscriptions; use fetch() to iterate
     */
    public static function bySubscriber($profile_id, $offset = 0, $limit = PROFILES_PER_PAGE)
    {
        // "by subscriber" means it is the list of subscribed users we want
        $ids = self::getSubscribedIDs($profile_id, $offset, $limit);
        return Subscription::listFind('subscribed', $ids);
    }

    /**
     * Stream of subscriptions with the same subscriber
     *
     * Useful for showing pages that list subscriptions in reverse
     * chronological order. Has offset & limit to make paging
     * easy.
     *
     * @param integer $profile_id   ID of the subscribed profile
     * @param integer $offset       Offset from latest
     * @param integer $limit        Maximum number to fetch
     *
     * @return Subscription stream of subscriptions; use fetch() to iterate
     */
    public static function bySubscribed($profile_id, $offset = 0, $limit = PROFILES_PER_PAGE)
    {
        // "by subscribed" means it is the list of subscribers we want
        $ids = self::getSubscriberIDs($profile_id, $offset, $limit);
        return Subscription::listFind('subscriber', $ids);
    }


    // The following are helper functions to the subscription lists,
    // notably the public ones get used in places such as Profile
    public static function getSubscribedIDs($profile_id, $offset, $limit) {
        return self::getSubscriptionIDs('subscribed', $profile_id, $offset, $limit);
    }

    public static function getSubscriberIDs($profile_id, $offset, $limit) {
        return self::getSubscriptionIDs('subscriber', $profile_id, $offset, $limit);
    }

    private static function getSubscriptionIDs($get_type, $profile_id, $offset, $limit)
    {
        switch ($get_type) {
        case 'subscribed':
            $by_type  = 'subscriber';
            break;
        case 'subscriber':
            $by_type  = 'subscribed';
            break;
        default:
            throw new Exception('Bad type argument to getSubscriptionIDs');
        }

        $cacheKey = 'subscription:by-'.$by_type.':'.$profile_id;

        $queryoffset = $offset;
        $querylimit = $limit;

        if ($offset + $limit <= self::CACHE_WINDOW) {
            // Oh, it seems it should be cached
            $ids = self::cacheGet($cacheKey);
            if (is_array($ids)) {
                return array_slice($ids, $offset, $limit);
            }
            // Being here indicates we didn't find anything cached
            // so we'll have to fill it up simultaneously
            $queryoffset = 0;
            $querylimit  = self::CACHE_WINDOW;
        }

        $sub = new Subscription();
        $sub->$by_type = $profile_id;
        $sub->selectAdd($get_type);
        $sub->whereAdd("{$get_type} != {$profile_id}");
        $sub->orderBy('created DESC');
        $sub->limit($queryoffset, $querylimit);

        if (!$sub->find()) {
            return array();
        }

        $ids = $sub->fetchAll($get_type);

        // If we're simultaneously filling up cache, remember to slice
        if ($offset === 0 && $querylimit === self::CACHE_WINDOW) {
            self::cacheSet($cacheKey, $ids);
            return array_slice($ids, $offset, $limit);
        }

        return $ids;
    }

    /**
     * Flush cached subscriptions when subscription is updated
     *
     * Because we cache subscriptions, it's useful to flush them
     * here.
     *
     * @param mixed $dataObject Original version of object
     *
     * @return boolean success flag.
     */
    function update($dataObject=false)
    {
        self::blow('subscription:by-subscriber:'.$this->subscriber);
        self::blow('subscription:by-subscribed:'.$this->subscribed);

        return parent::update($dataObject);
    }

    function getURI()
    {
        if (!empty($this->uri)) {
            return $this->uri;
        } else {
            return self::newURI($this->subscriber, $this->subscribed, $this->created);
        }
    }

    static function newURI($subscriber_id, $subscribed_id, $created)
    {
        return TagURI::mint('follow:%d:%d:%s',
                            $subscriber_id,
                            $subscribed_id,
                            common_date_iso8601($created));
    }
}

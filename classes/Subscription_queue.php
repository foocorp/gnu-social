<?php
/**
 * Table Definition for subscription_queue
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Subscription_queue extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'subscription_queue';       // table name
    public $subscriber;
    public $subscribed;
    public $created;

    /* Pkey get */
    function pkeyGet($k)
    { return Memcached_DataObject::pkeyGet('Subscription_queue',$k); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Holder for subscription requests awaiting moderation.',
            'fields' => array(
                'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile making the request'),
                'subscribed' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile being subscribed to'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('subscriber', 'subscribed'),
            'indexes' => array(
                'subscription_queue_subscriber_created_idx' => array('subscriber', 'created'),
                'subscription_queue_subscribed_created_idx' => array('subscribed', 'created'),
            ),
            'foreign keys' => array(
                'subscription_queue_subscriber_fkey' => array('profile', array('subscriber' => 'id')),
                'subscription_queue_subscribed_fkey' => array('profile', array('subscribed' => 'id')),
            )
        );
    }

    public static function saveNew(Profile $subscriber, Profile $subscribed)
    {
        $rq = new Subscription_queue();
        $rq->subscriber = $subscriber->id;
        $rq->subscribed = $subscribed->id;
        $rq->created = common_sql_now();
        $rq->insert();
        return $rq;
    }

    function exists($subscriber, $other)
    {
        $sub = Subscription_queue::pkeyGet(array('subscriber' => $subscriber->id,
                                                 'subscribed' => $other->id));
        return (empty($sub)) ? false : true;
    }

    /**
     * Complete a pending subscription, as we've got approval of some sort.
     *
     * @return Subscription
     */
    public function complete()
    {
        $subscriber = Profile::getKV('id', $this->subscriber);
        $subscribed = Profile::getKV('id', $this->subscribed);
        $sub = Subscription::start($subscriber, $subscribed, Subscription::FORCE);
        if ($sub) {
            $this->delete();
        }
        return $sub;
    }

    /**
     * Cancel an outstanding subscription request to the other profile.
     */
    public function abort()
    {
        $subscriber = Profile::getKV('id', $this->subscriber);
        $subscribed = Profile::getKV('id', $this->subscribed);
        if (Event::handle('StartCancelSubscription', array($subscriber, $subscribed))) {
            $this->delete();
            Event::handle('EndCancelSubscription', array($subscriber, $subscribed));
        }
    }

    /**
     * Send notifications via email etc to group administrators about
     * this exciting new pending moderation queue item!
     */
    public function notify()
    {
        $other = Profile::getKV('id', $this->subscriber);
        $listenee = User::getKV('id', $this->subscribed);
        mail_subscribe_pending_notify_profile($listenee, $other);
    }
}

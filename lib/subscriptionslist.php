<?php

if (!defined('GNUSOCIAL')) { exit(1); }

// XXX SubscriptionsList and SubscriptionList are dangerously close

class SubscriptionsList extends SubscriptionList
{
    function newListItem(Profile $profile)
    {
        return new SubscriptionsListItem($profile, $this->owner, $this->action);
    }
}

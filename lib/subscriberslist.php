<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class SubscribersList extends SubscriptionList 
{ 
    function newListItem(Profile $profile) 
    { 
        return new SubscribersListItem($profile, $this->owner, $this->action); 
    } 
}

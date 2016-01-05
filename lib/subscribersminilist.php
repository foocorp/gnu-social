<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class SubscribersMiniList extends ProfileMiniList
{
    public function newListItem(Profile $profile)
    {
        return new SubscribersMiniListItem($profile, $this->action);
    }
}

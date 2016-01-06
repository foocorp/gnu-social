<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class GroupBlockedMiniList extends ProfileMiniList
{
    function newListItem(Profile $profile)
    {
        return new GroupBlockedMiniListItem($profile, $this->action);
    }
}

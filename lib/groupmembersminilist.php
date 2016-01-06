<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class GroupMembersMiniList extends ProfileMiniList
{
    function newListItem(Profile $profile)
    {
        return new GroupMembersMiniListItem($profile, $this->action);
    }
}

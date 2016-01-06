<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class GroupBlockedMiniListItem extends ProfileMiniListItem
{
    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }
}

<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ProfileNoticeList extends NoticeList
{
    function newListItem($notice)
    {
        return new ProfileNoticeListItem($notice, $this->out);
    }
}

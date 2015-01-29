<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ApiTimelineNetworkPublicAction extends ApiTimelinePublicAction
{
    function title()
    {
        return sprintf(_("%s network public timeline"), common_config('site', 'name'));
    }

    protected function getStream()
    {
        return new NetworkPublicNoticeStream($this->scoped);
    }
}
